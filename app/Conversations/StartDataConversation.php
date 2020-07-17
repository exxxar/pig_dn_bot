<?php

namespace App\Conversations;

use App\CashBackHistory;
use App\User;

use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use Carbon\Carbon;
use DateTime;
use Illuminate\Foundation\Inspiring;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class StartDataConversation extends Conversation
{

    protected $data;
    protected $bot;
    protected $code;
    protected $request_user_id;
    protected $user;
    protected $check_info;
    protected $beer_in_check;

    public function createUser()
    {
        $telegramUser = $this->bot->getUser();
        $id = $telegramUser->getId();
        $username = $telegramUser->getUsername();
        $lastName = $telegramUser->getLastName();
        $firstName = $telegramUser->getFirstName();

        $user = User::where("telegram_chat_id", $id)->first();
        $parent = User::where("telegram_chat_id", intval($this->request_user_id))->first();

        if ($user == null)
            $user = \App\User::create([
                'name' => $username ?? "$id",
                'email' => "$id@t.me",
                'password' => bcrypt($id),
                'fio_from_telegram' => "$firstName $lastName",
                'telegram_chat_id' => $id,
                'is_admin' => false,
                'is_vip' => false,
                'is_working' => false,
                'cashback_money'=>0,
                'cashback_beer'=>0,
                'phone' => '',
                'parent_id' => $parent->id ?? null,
                'birthday' => '',
            ]);
        return $user;
    }

    public function mainMenu($message)
    {
        $telegramUser = $this->bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        if (is_null($user))
            $user = $this->createUser();


        $keyboard = [];

        array_push($keyboard, ["\xF0\x9F\x8D\xB1Новое меню"]);
        if (!$user->is_vip)
            array_push($keyboard, ["\xE2\x9A\xA1Анкета VIP-пользователя"]);
        else
            array_push($keyboard, ["\xE2\x9A\xA1Special BeerBack system"]);

        //array_push($keyboard, ["\xF0\x9F\x8E\xB0Розыгрыш"]);
        array_push($keyboard, ["\xF0\x9F\x92\xADО Нас"]);

        if ($user->is_admin)
            array_push($keyboard, ["\xE2\x9A\xA0Админ. статистика"]);


        $this->bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "text" => $message,
                "parse_mode" => "Markdown",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => false,
                    'resize_keyboard' => true
                ])
            ]);
    }

    public function __construct($bot, $data)
    {

        $this->bot = $bot;
        $this->data = $data;
        $this->code = null;
        $this->request_user_id = null;
        $this->user = null;
        $this->check_info = '';
        $this->beer_in_check = 0;
    }

    /**
     * Start the conversation
     */
    public function run()
    {
        try {
            $this->startWithData();
        } catch (\Exception $e) {
            Log::error(get_class($this) . " " . $e->getMessage() . " " . $e->getLine());
        }
    }

    /**
     * First question
     */
    public function startWithData()
    {
        $pattern = "/([0-9]{3})([0-9]{10})/";
        $string = base64_decode($this->data);

        $is_valid = preg_match_all($pattern, $string, $matches);

        if (!$is_valid) {
            $this->mainMenu("Главное меню");
            return;
        }

        $this->code = $matches[1][0];
        $this->request_user_id = $matches[2][0];

        $telegramUser = $this->bot->getUser();
        $id = $telegramUser->getId();

        $this->user = User::where("telegram_chat_id", $id)->first();

        if (!is_null($this->user))
            if (!$this->user->is_admin) {
                $this->mainMenu("Главное меню");
                return;
            }

        if (is_null($this->user)) {
            $this->user = $this->createUser();

            Telegram::sendMessage([
                'chat_id' => intval($this->request_user_id),
                'parse_mode' => 'Markdown',
                'text' => "По вашей реферальной ссылке перешел пользователь " . (
                        $this->user->fio_from_telegram ??
                        $this->user->phone ??
                        $this->user->name ??
                        $this->user->email
                    ),
            ]);

            $this->mainMenu("Главное меню: _спасибо что пешерешли по реферальной ссылке!_");
            return;

        }

        Log::info("TEST ".$this->code." ".$this->request_user_id);


        if ($this->code == "005") {

            Log::info("TEST 2=".$this->user->telegram_chat_id);
            $tmp_user_id = (string)$this->user->telegram_chat_id;
            while (strlen($tmp_user_id) < 10)
                $tmp_user_id = "0" . $tmp_user_id;

            $code = base64_encode("001" . $tmp_user_id);
            $url_link = "https://t.me/" . env("APP_BOT_NAME") . "?start=$code";
            Log::info("TEST 3=".$url_link);
            $keyboard = [
                [
                    ['text' => "Запустить систему BeerBack", 'url' => "$url_link"],

                ]
            ];
            Log::info("TEST 4 $code");
            Telegram::sendMessage([
                'chat_id' => $this->request_user_id,
                'parse_mode' => 'Markdown',
                'text' => "Test"/* sprintf("Пользователь %s (%s) хочет воспользоваться услугой BeerBack",
                    ($this->user->name ?? $this->user->fio_from_telegram ?? $this->user->telegram_chat_id??"Ошибка"),
                    ($this->user->phone ?? "У пользователя нет телефонного номера"))*/,
                'reply_markup' => json_encode([
                    'inline_keyboard' =>
                        $keyboard
                ])
            ]);
            Log::info("TEST 5");
            $this->mainMenu("Главное меню");
            return;
        }

        if (!$this->user->is_admin) {
            $this->mainMenu("Недостаточно прав доступа для совершения данной операции");
            return;
        }

        if ($this->code == "001")
            $this->askForAction();
        else
            $this->mainMenu("Неопознанный код операции");


    }


    public function askForAction()
    {
        $question = Question::create("Какое действие выполнить?")
            ->addButtons([
                Button::create("Списать BeerBack")->value('askforpay'),
                Button::create("Начислить BeerBack")->value('addcashback'),
                Button::create("Добавить администратора")->value('addadmin'),
                Button::create("Убрать администратора")->value('removeadmin'),
                Button::create("Завершить работу")->value('stopcashback'),


            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $selectedValue = $answer->getValue();

                if ($selectedValue == "askforpay") {
                    $this->askForPay();
                }

                if ($selectedValue == "addcashback") {
                    $this->askForCashback();
                }

                if ($selectedValue == "addadmin") {
                    $this->askForAddAdmin(true);
                }

                if ($selectedValue == "removeadmin") {
                    $this->askForAddAdmin(false);
                }


                if ($selectedValue == "stopcashback")
                    return;

            }
        });
    }

    public function askForAddAdmin($flag = true)
    {
        $recipient_user = User::where("telegram_chat_id", intval($this->request_user_id))->first();
        if (!$recipient_user) {
            $this->mainMenu("Пользователь не найден!");
            return;
        }

        $recipient_user->is_admin = $flag;
        $recipient_user->save();


        $this->bot->reply(sprintf(($flag?"Пользователь %s успшено назанчен администратором!":"Пользователь %s снят с должности администратора!"), ($recipient_user->phone ?? $recipient_user->fio_from_telegram ?? $recipient_user->name)));

        Telegram::sendMessage([
            'chat_id' => $recipient_user->telegram_chat_id,
            'parse_mode' => 'Markdown',
            'text' => ($flag?"Вы назначены администратором!":"Вас сняли с должности администратора!"),
        ]);
    }

    public function askForPay()
    {
        $recipient_user = User::where("telegram_chat_id", intval($this->request_user_id))->first();
        if (!$recipient_user) {
            $this->mainMenu("Пользователь не найден!");
            return;
        }

        $this->bot->reply("У пользователя " . $recipient_user->cashback_beer . " литров BeerBack-а");

        $question = Question::create("Введите кол-во для списания:")
            ->fallback(__("messages.ask_fallback"));

        $this->ask($question, function (Answer $answer) use ($recipient_user) {
            $nedded_bonus = $answer->getText();

            if (strlen(trim($nedded_bonus)) == 0 || !is_numeric($nedded_bonus)) {
                $this->askForPay();
                return;
            }

            $canPay = $recipient_user->cashback_money >= intval($nedded_bonus);

            if (!$canPay) {
                $this->mainMenu("У пользователя недостаточно BeerBack-а!");

                Telegram::sendMessage([
                    'chat_id' => $recipient_user->telegram_chat_id,
                    'parse_mode' => 'Markdown',
                    'text' => "Требуется списать $nedded_bonus литров пива. У вас в наличии: " . $recipient_user->cashback_beer . " литров.",
                ]);
                return;
            }

            CashBackHistory::create([
                'amount' => $nedded_bonus,
                'bill_number' => "Списание пива",
                'money_in_bill' => $nedded_bonus,
                'employee_id' => $this->user->id,
                'user_id' => $recipient_user->id,
                'type' => 1,
            ]);

            $recipient_user->cashback_money -= $nedded_bonus;
            $recipient_user->save();

            Telegram::sendMessage([
                'chat_id' => $recipient_user->telegram_chat_id,
                'parse_mode' => 'Markdown',
                'text' => "С вашего пивного счета произведено списание $nedded_bonus литров пива!",
            ]);

            $this->mainMenu("Спасибо! Успешно списалось $nedded_bonus литров пива. Остаток:" . $recipient_user->cashback_money . " литров.");

            $this->askForAction();
            return;


        });
    }

    public function askForCashback()
    {
        $question = Question::create("Введите кол-во литров пива из чека")
            ->fallback(__("messages.ask_fallback"));

        $this->ask($question, function (Answer $answer) {
            $this->beer_in_check = $answer->getText();
            if (strlen(trim($this->beer_in_check)) == 0 || !is_numeric($this->beer_in_check)) {
                $this->askForCashback();
                return;
            }

            $this->askForCheckInfo();
        });
    }

    public function askForCheckInfo()
    {
        $question = Question::create("Введите номер чека")
            ->fallback(__("messages.ask_fallback"));

        $this->ask($question, function (Answer $answer) {
            $this->check_info = $answer->getText();
            if (strlen(trim($this->check_info)) == 0) {
                $this->askForCheckInfo();
                return;
            }
            $this->saveCashBack();
        });
    }

    public function saveCashBack()
    {

        $recipient_user = User::with(["parent"])->where("telegram_chat_id", intval($this->request_user_id))->first();

        if (!$recipient_user) {
            $this->mainMenu("Пользователь не найден!");
            return;
        }

        $cashback = ((intval($this->beer_in_check) ?? 0) * env("CAHSBAK_PROCENT") / 100);
        $parent_cashback = ((intval($this->beer_in_check) ?? 0) * env("NETWORK_CAHSBAK_PROCENT") / 100);

        $recipient_user->cashback_beer += $cashback;
        $recipient_user->save();

        if (!is_null($recipient_user->parent_id)) {

            $parent = $recipient_user->parent;
            $parent->cashback_beer += $parent_cashback;
            $parent->save();


            CashBackHistory::create([
                'amount' => $parent_cashback,
                'bill_number' => "BeerBack от друга",
                'money_in_bill' => $parent_cashback,
                'employee_id' => $this->user->id,
                'user_id' => $parent->id,
                'type' => 0,
            ]);


            Telegram::sendMessage([
                'chat_id' => $parent->telegram_chat_id,
                'parse_mode' => 'Markdown',
                'text' => "Ваш друг " . (
                        $recipient_user->fio_from_telegram ??
                        $recipient_user->phone ??
                        $recipient_user->name ??
                        $recipient_user->email
                    ) . " принес Вам $parent_cashback литров пива BeerBack-а",
            ]);


        }


        CashBackHistory::create([
            'amount' => $cashback,
            'bill_number' => $this->check_info,
            'money_in_bill' => $this->beer_in_check,
            'employee_id' => $this->user->id,
            'user_id' => $recipient_user->id,
            'type' => 0,
        ]);

        $this->mainMenu("Отлично! BeerBack $cashback литров пива начислен пользователю " . (
                $recipient_user->fio_from_telegram ??
                $recipient_user->phone ??
                $recipient_user->name ??
                $recipient_user->email
            )
        );

        try {
            Telegram::sendMessage([
                'chat_id' => $recipient_user->telegram_chat_id,
                'parse_mode' => 'Markdown',
                'text' => "На ваш пивной счет начислено $cashback литров пива.",
            ]);
        } catch (\Exception $e) {

        }


    }

}
