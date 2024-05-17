<?php

namespace App\Handlers;

use App\Enums\QuestionsWithVariants;
use App\Models\User;
use App\Services\Spreadsheet\Classes\SpreadsheetService;
use App\Services\Spreadsheet\Interfaces\SpreadsheetServiceInterface;
use DefStudio\Telegraph\{Facades\Telegraph, Handlers\WebhookHandler, Keyboard\Button, Keyboard\Keyboard};
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{Log, Redis};
use Illuminate\Support\Stringable;

class TelegramHandler extends WebhookHandler
{
    /**
     * Question list
     *
     * @var array
     */
    private const QUESTIONS = [
        0 => "Введите сумму",
        1 => "Введите статью расходов",
        2 => "Выберите статус суммы",
        3 => "Дайте имя операции",
        4 => "Введите дату операции формата дд.мм.гггг",
        5 => "Подтвердите сохранение",
    ];

    /**
     * Questions with variants list
     *
     * @var array
     */
    private const QUESTIONS_WITH_VARIANTS = [
        1 => self::QUESTIONS[1],
        2 => self::QUESTIONS[2],
        5 => self::QUESTIONS[5],
    ];

    /**
     * Get integer value from button question
     *
     * @param QuestionsWithVariants $questionsWithVariants
     * @return int
     */
    public function getQuestion(QuestionsWithVariants $questionsWithVariants): int
    {
        return match ($questionsWithVariants) {
            QuestionsWithVariants::FirstButtonQuestion => 1,
            QuestionsWithVariants::SecondButtonQuestion => 2,
            QuestionsWithVariants::ThirdButtonQuestion => 5,
        };
    }

    /**
     * Initial point
     * @return void
     */
    public function start(): void
    {
        if (is_null(Redis::get('userId'))) {
            Redis::set('userId', $this->message->from()->id());
        }

        if (User::where('telegram_user_id', $this->message->from()->id())->count() === 0) {
            User::insert([
                'telegram_user_id' => $this->message->from()->id()
            ]);
        }

        Telegraph::message('Внесите ID вашей таблицы командой /addTable *table_id*')->send();
    }

    /**
     * Adding table
     * @param $tableId
     * @return void
     */
    public function addTable($tableId)
    {
        User::where('telegram_user_id', $this->message->from()->id())
            ->first()
            ->update([
                'table_id' => $tableId
            ]);

        Telegraph::message('Готово! Чтобы начать работу, используйте команду /insert')->send();
    }

    /**
     * Initial insert method
     * @return void
     */
    public function insert()
    {

        $userId = Redis::get('userId');

        $keys = Redis::command('keys', ["user:$userId:*"]);

        foreach ($keys as $key) {
            Redis::command('del', [$key]);
        }

        Redis::set("user:$userId:question", 0);

        Telegraph::message(self::QUESTIONS[0])->send();
    }

    /**
     * Message handler
     * @param Stringable $text
     * @param int|null $uId
     * @return void
     */
    protected function handleChatMessage(Stringable $text, ?int $uId = null): void
    {
        $userId = $uId;
        if (is_null($uId)) {
            $userId = $this->message->from()->id();
        }

        $questionId = Redis::get("user:{$userId}:question");

        if (is_null($questionId)) {
            $questionId = 0;
        }

        if (!in_array(Arr::get(self::QUESTIONS, $questionId), self::QUESTIONS_WITH_VARIANTS)) {
            Redis::set("user:$userId:answer:$questionId", $text);
        }

        $nextQuestionKey = ++$questionId;
        $nextQuestion = Arr::get(self::QUESTIONS, $nextQuestionKey);
        $getKeyOfValue = array_search($nextQuestion, self::QUESTIONS);
        Redis::set("user:$userId:question", $nextQuestionKey);

        if (in_array($nextQuestion, self::QUESTIONS_WITH_VARIANTS)) {
            if ($this->getQuestion(QuestionsWithVariants::FirstButtonQuestion) === (int)$getKeyOfValue) {
                $this->expenseItemList((int)$getKeyOfValue, $questionId, $userId);
            }

            if ($this->getQuestion(QuestionsWithVariants::SecondButtonQuestion) === (int)$getKeyOfValue) {
                $this->statusList((int)$getKeyOfValue, $questionId, $userId);
            }

            if ($this->getQuestion(QuestionsWithVariants::ThirdButtonQuestion) === (int)$getKeyOfValue) {
                $this->agreeList((int)$getKeyOfValue, $questionId);
            }

            return;
        }

        if (is_null(Arr::get(self::QUESTIONS, $nextQuestionKey))) {
            $this->reply('done');
        }

        Telegraph::message(Arr::get(self::QUESTIONS, $nextQuestionKey, ''))->send();
    }

    /**
     * Telegram button generator
     * @param int $getKeyOfValue
     * @param int $questionId
     * @param int $userId
     * @return void
     * @throws \JsonException
     */
    private function expenseItemList(int $getKeyOfValue, int $questionId, int $userId): void
    {
        $setOfQuestions = [];

        foreach (SpreadsheetService::getConditionElements($userId) as $key => $value) {
            $setOfQuestions[$key] = Button::make($value)
                ->action('saveData')
                ->param('value', $key)
                ->param('userId', $userId)
                ->param('key', $questionId);
        }

        Telegraph::message(self::QUESTIONS[$getKeyOfValue])
            ->keyboard(Keyboard::make()->buttons(
                $setOfQuestions
            ))->send();
    }

    /**
     * Income/outcome choice
     * @param int $getKeyOfValue
     * @param int $questionId
     * @param int $userId
     * @return void
     */
    private function statusList(int $getKeyOfValue, int $questionId, int $userId): void
    {
        Telegraph::message(self::QUESTIONS[$getKeyOfValue])
            ->keyboard(Keyboard::make()->buttons([
                Button::make('Приход')->action('saveData')
                    ->param('value', 0)
                    ->param('userId', $userId)
                    ->param('key', $questionId),
                Button::make('Расход')->action('saveData')
                    ->param('value', 1)
                    ->param('userId', $userId)
                    ->param('key', $questionId)
            ]))->send();
    }

    /**
     * Save/clean choice
     * @param int $getKeyOfValue
     * @param int $questionId
     * @return void
     */
    private function agreeList(int $getKeyOfValue, int $questionId): void
    {
        Telegraph::message(self::QUESTIONS[$getKeyOfValue])
            ->keyboard(Keyboard::make()->buttons([
                Button::make('Сохранить')->action('saveToTable')
                    ->param('userId', Redis::get('userId')),
                Button::make('Стереть')->action('again')
                    ->param('key', $questionId)
            ]))->send();
    }

    /**
     * Print all chosen variants to google spreadsheet
     * @param $userId
     * @return void
     * @throws BindingResolutionException
     */
    public function saveToTable($userId): void
    {
        $service = app()->make(SpreadsheetServiceInterface::class);

        $service->saveRecord([
            Redis::command("get", ["user:$userId:answer:4"]), // дата
            Redis::command("get", ["user:$userId:answer:3"]), // имя
            Redis::command("get", ["user:$userId:answer:1"]), // статья расходов
            (int)Redis::command("get", ["user:$userId:answer:2"]) ? Redis::command("get", ["user:$userId:answer:0"]) : 0,
            (int)Redis::command("get", ["user:$userId:answer:2"]) ? 0 : Redis::command("get", ["user:$userId:answer:0"]),
        ], $userId);

        $keys = Redis::command('keys', ["user:$userId:*"]);
        foreach ($keys as $key) {
            Redis::command('del', [$key]);
        }
        $this->insert();
    }

    /**
     * Method for saving buttons' values
     * @param $value
     * @param $key
     * @param $userId
     * @return void
     */
    public function saveData($value, $key, $userId): void
    {
        if (1 === (int)$key) {
            $newValue = SpreadsheetService::getConditionElements($userId)[(int)$value];
            Redis::set("user:{$userId}:answer:{$key}", $newValue);

            $this->handleChatMessage(new Stringable(''), $userId);

            return;
        }

        Redis::set("user:{$userId}:answer:{$key}", (int)$value);

        $this->handleChatMessage(new Stringable(''), $userId);
    }

    /**
     * Start over
     * @return void
     */
    public function again(): void
    {
        $this->insert();
    }

    /**
     * Handle unknown command from telegram
     * @param Stringable $text
     * @return void
     */
    protected function handleUnknownCommand(Stringable $text): void
    {
        $this->reply('Неизвестная команда');
    }
}
