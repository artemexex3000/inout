<?php

namespace App\Http\Telegram;

use App\Services\Spreadsheet\Interfaces\SpreadsheetServiceInterface;
use DefStudio\Telegraph\{Facades\Telegraph, Handlers\WebhookHandler, Keyboard\Button, Keyboard\Keyboard};
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{Log, Redis};
use Illuminate\Support\Stringable;


class Handler extends WebhookHandler
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
     * The second question's variants list
     *
     * @var array
     */
    private const SECOND_QUESTION_VARIANTS = [
        'ЗП - менеджеры', 'ЗП - тех. отдел', 'ЗП - IT отдел', 'ЗП - контент', 'ЗП - бухгалтерия', 'ЗП - юр. отдел',
        'Номера рассылок', 'Офис', 'Расходники', 'ПО и сервера', 'Реклама', 'Техника', 'Телефония', 'Неофиц. кабинет',
        'Налог', 'ДРУГОЕ', 'Официальная', 'ОСОБАЯ', 'Пополнение Офиц. кабинета', 'База', 'СМС', 'Пополнение Online',
        'Чат реклама', 'ПРИГЛАШЕНИЕ', 'База Сообщества', 'База Возраст', 'ПРИГЛАШЕНИЕ S', 'ПРИГЛАШЕНИЕ M',
    ];

    /**
     * Initial point
     * @return void
     */
    public function start(): void
    {
        if (is_null(Redis::get('userId'))) {
            Redis::set('userId', $this->message->from()->id());
        }

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
            if (1 === (int)$getKeyOfValue) {
                $this->expenseItemList((int)$getKeyOfValue, $questionId, $userId);
            }

            if (2 === (int)$getKeyOfValue) {
                $this->statusList((int)$getKeyOfValue, $questionId, $userId);
            }

            if (5 === (int)$getKeyOfValue) {
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
     */
    private function expenseItemList(int $getKeyOfValue, int $questionId, int $userId): void
    {
        Telegraph::message(self::QUESTIONS[$getKeyOfValue])
            ->keyboard(Keyboard::make()->buttons([
                Button::make('ЗП - менеджеры')->action('saveData')->param('value', 0)->param('userId', $userId)->param('key', $questionId),
                Button::make('ЗП - тех. отдел')->action('saveData')->param('value', 1)->param('userId', $userId)->param('key', $questionId),
                Button::make('ЗП - IT отдел')->action('saveData')->param('value', 2)->param('userId', $userId)->param('key', $questionId),
                Button::make('ЗП - контент')->action('saveData')->param('value', 3)->param('userId', $userId)->param('key', $questionId),
                Button::make('ЗП - бухгалтерия')->action('saveData')->param('value', 4)->param('userId', $userId)->param('key', $questionId),
                Button::make('ЗП - юр. отдел')->action('saveData')->param('value', 5)->param('userId', $userId)->param('key', $questionId),
                Button::make('Номера рассылок')->action('saveData')->param('value', 6)->param('userId', $userId)->param('key', $questionId),
                Button::make('Офис')->action('saveData')->param('value', 7)->param('userId', $userId)->param('key', $questionId),
                Button::make('Расходники')->action('saveData')->param('value', 8)->param('userId', $userId)->param('key', $questionId),
                Button::make('ПО и сервера')->action('saveData')->param('value', 9)->param('userId', $userId)->param('key', $questionId),
                Button::make('Реклама')->action('saveData')->param('value', 10)->param('userId', $userId)->param('key', $questionId),
                Button::make('Техника')->action('saveData')->param('value', 11)->param('userId', $userId)->param('key', $questionId),
                Button::make('Телефония')->action('saveData')->param('value', 12)->param('userId', $userId)->param('key', $questionId),
                Button::make('Неофиц. кабинет')->action('saveData')->param('value', 13)->param('userId', $userId)->param('key', $questionId),
                Button::make('Налог')->action('saveData')->param('value', 14)->param('userId', $userId)->param('key', $questionId),
                Button::make('ДРУГОЕ')->action('saveData')->param('value', 15)->param('userId', $userId)->param('key', $questionId),
                Button::make('Официальная')->action('saveData')->param('value', 16)->param('userId', $userId)->param('key', $questionId),
                Button::make('ОСОБАЯ')->action('saveData')->param('value', 17)->param('userId', $userId)->param('key', $questionId),
                Button::make('Пополнение Офиц. кабинета')->action('saveData')->param('value', 18)->param('user_id', $userId)->param('key', $questionId),
                Button::make('База')->action('saveData')->param('value', 19)->param('userId', $userId)->param('key', $questionId),
                Button::make('СМС')->action('saveData')->param('value', 20)->param('userId', $userId)->param('key', $questionId),
                Button::make('Пополнение Online')->action('saveData')->param('value', 21)->param('userId', $userId)->param('key', $questionId),
                Button::make('Чат реклама')->action('saveData')->param('value', 22)->param('userId', $userId)->param('key', $questionId),
                Button::make('ПРИГЛАШЕНИЕ')->action('saveData')->param('value', 23)->param('userId', $userId)->param('key', $questionId),
                Button::make('База Сообщества')->action('saveData')->param('value', 24)->param('userId', $userId)->param('key', $questionId),
                Button::make('База Возраст')->action('saveData')->param('value', 25)->param('userId', $userId)->param('key', $questionId),
                Button::make('ПРИГЛАШЕНИЕ S')->action('saveData')->param('value', 26)->param('userId', $userId)->param('key', $questionId),
                Button::make('ПРИГЛАШЕНИЕ M')->action('saveData')->param('value', 27)->param('userId', $userId)->param('key', $questionId),
            ]))->send();
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
                Button::make('Приток')->action('saveData')
                    ->param('value', 0)
                    ->param('userId', $userId)
                    ->param('key', $questionId),
                Button::make('Убыток')->action('saveData')
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
        ]);

        $keys = Redis::command('keys', ["user:$userId:*"]);
        foreach ($keys as $key) {
            Redis::command('del', [$key]);
        }
        $this->start();
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
        Log::error($key);
        if (1 === (int)$key) {
            $newValue = self::SECOND_QUESTION_VARIANTS[(int)$value];
            Redis::set("user:{$userId}:answer:{$key}", $newValue);
        }
        if (2 === (int)$key) {
            Redis::set("user:{$userId}:answer:{$key}", (int)$value);
        }

        $stringable = new Stringable('');
        $this->handleChatMessage($stringable, $userId);
    }

    /**
     * Start over
     * @return void
     */
    public function again(): void
    {
        $this->start();
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
