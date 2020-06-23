<?php

use App\Http\Controllers\BotManController;
use App\Prize;
use App\Product;
use App\User;
use BotMan\BotMan\BotMan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

$botman = resolve('botman');

function createUser($bot)
{
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();
    $username = $telegramUser->getUsername();
    $lastName = $telegramUser->getLastName();
    $firstName = $telegramUser->getFirstName();

    $user = User::where("telegram_chat_id", $id)->first();
    if ($user == null)
        $user = \App\User::create([
            'name' => $username ?? "$id",
            'email' => "$id@t.me",
            'password' => bcrypt($id),
            'fio_from_telegram' => "$firstName $lastName",
            'telegram_chat_id' => $id,
            'is_admin' => false,
            'is_vip' => false,
            'cashback_money' => false,
            'phone' => '',
            'birthday' => '',
        ]);
    return $user;
}
function mainMenu($bot, $message)
{
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id",$id)->first();

    if (is_null($user))
        $user=createUser($bot);


    $keyboard = [

    ];

    array_push($keyboard, ["\xF0\x9F\x8D\xB1Новое меню"]);
    if (!$user->is_vip)
        array_push($keyboard, ["\xE2\x9A\xA1Анкета VIP-пользователя"]);
    else
        array_push($keyboard, ["\xE2\x9A\xA1Special BeerBack system"]);

    array_push($keyboard,["\xF0\x9F\x8E\xB0Розыгрыш"]);
    array_push($keyboard,["\xF0\x9F\x92\xADО Нас"]);

    $bot->sendRequest("sendMessage",
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

$botman->hears(".*Анкета VIP-пользователя|/do_vip", BotManController::class . "@vipConversation");
$botman->hears('.*Розыгрыш', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $keybord = [
        [
            ['text' => "Условия розыгрыша и призы", 'url' => "https://telegra.ph/Usloviya-rozygrysha-01-01"]
        ],
        [
            ['text' => "Ввести код и начать", 'callback_data' => "/lottery"]
        ]
    ];
    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "Розыгрыш призов",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keybord
            ])
        ]);
});
$botman->hears('.*О нас', function ($bot) {
    $bot->reply("https://telegra.ph/O-Nas-06-23");
});

$botman->hears("/start ([0-9a-zA-Z=]+)", BotManController::class . '@startDataConversation');
$botman->hears('/start', function ($bot) {
    createUser($bot);
    mainMenu($bot, 'Главное меню');
})->stopsConversation();
$botman->hears('.*Новое меню', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();


   /* $keyboard = [
        [
            ['text' => "\xF0\x9F\x93\x8BОформить заказ", 'url' => "https://isushi-dn.ru"],
        ],
    ];*/

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "https://telegra.ph/Menyu-06-23-2",
        ]);
});
$botman->hears('.*Special BeerBack system', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    if (is_null($user))
        $user = createUser($bot);

    $beerback = $user->cashback_beer ?? 0;

    $is_vip = $user->is_vip ?? false;


    if (!$is_vip) {
        $keyboard = [
            [
                ['text' => "\xF0\x9F\x8D\xB8Оформить VIP-статус", 'callback_data' => "/do_vip"],
            ],
        ];
        $bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "parse_mode" => "markdown",
                "text" => "У вас нет VIP-статуса, но вы можете его оформить!",
                'reply_markup' => json_encode([
                    'inline_keyboard' =>
                        $keyboard
                ])
            ]);

        return;
    }

    $message = sprintf("У вас *%s* литров пива!\n_Для начисления BeerBack литров при оплате за меню дайте отсканировать данный QR-код нашему сотруднику_", $beerback);
    $keyboard = [
        [
            ['text' => "Мой пивной бюджет", 'callback_data' => "/my_beer"],
        ],
    ];

    /*$keyboard2 = [
        [
            ['text' => "Подробности на сайте", 'url' => "https://isushi-dn.ru"],
        ],
    ];*/

    $tmp_id = (string)$id;
    while (strlen($tmp_id) < 10)
        $tmp_id = "0" . $tmp_id;

    $code = base64_encode("001" . $tmp_id);

    $qr_url = env("QR_URL") . "https://t.me/" . env("APP_BOT_NAME") . "?start=$code";

    /*$bot->sendRequest("sendPhoto",
        [
            "chat_id" => "$id",
            "photo"=>"https://psv4.userapi.com/c856324/u14054379/docs/d11/b44982ee5be8/cashback.png?extra=mpOQonv9nnoVOvkOde1vMX1R7Gn6sGBpT-yTsiOl_GyeIut9zHnt3YIxH77gwLS4cyu85tEEC4UjPd6fcmunhQWmH3kzjwbgWXb7Ithm9ik8yyTuPfrYNqoLOgYLjrIzmGYUhxEQKxoQ-C6EDqUtNQ",
            "caption" => "Теперь ты можешь получать 20% CashBack от всех твоих покупок и 3% от покукпок друзей! Для этого подключи друзей к данной системе!\n_Дай отсканировать QR-код друзьям или делись ссылкой_ *https://t.me/" . env("APP_BOT_NAME") . "?start=$code* _с друзьями и получай больше CashBack с каждой их покупки!_",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard2
            ])
        ]);*/

    $bot->sendRequest("sendPhoto",
        [
            "chat_id" => "$id",
            "caption" => "$message",
            "parse_mode" => "Markdown",
            "photo" => $qr_url,
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);

});
$botman->hears('/lottery', BotManController::class . '@lotteryConversation');
$botman->hears('/check_lottery_slot ([0-9]+)', function ($bot, $slotId) {


    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $prize = Prize::find($slotId);

    $message = "*" . $prize->title . "*\n"
        . "_" . $prize->description . "_\n";


    $bot->sendRequest("sendPhoto",
        [
            "chat_id" => "$id",
            "photo" => $prize->image_url,
            "caption" => $message,
            "parse_mode" => "Markdown",
        ]);

    $user = User::where("telegram_chat_id", $id)->first();

    try {

        Telegram::sendMessage([
            'chat_id' => env("CHANNEL_ID"),
            'parse_mode' => 'Markdown',
            'text' => sprintf(($prize->type === 0?"Заявка на получение приза":"*Пользователь получил виртуальный приз*")."\nНомер телефона:_%s_\nПриз: [#%s] \"%s\"",
                $user->phone,
                $prize[0]->id,
                $prize[0]->title),
            'disable_notification' => 'false'
        ]);
    } catch (\Exception $e) {
        Log::info("Ошибка отправки заказа в канал!");
    }


});
$botman->hears('/my_beer', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $keyboard = [
        [
            ['text' => "Получено", 'callback_data' => "/beerback_up"],
            ['text' => "Выпито", 'callback_data' => "/beerback_down"],
        ],
    ];

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "*Управление вашими начислениями и расходами BeerBack*",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);
});
$botman->hears('/beerback_up', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $keyboard = [
        [
            ['text' => "Выпито", 'callback_data' => "/beerback_down"],
        ],
    ];


    $user = User::where("telegram_chat_id", $id)->first();

    if (is_null($user))
        $user = createUser($bot);

    $cashback = \App\CashBackHistory::where("user_id", $user->id)
        ->where("type", 0)
        ->orderBy("id", "desc")
        ->take(20)
        ->skip(0)
        ->get();

    if (count($cashback) == 0)
        $message = "На текущий момент у вас нет бонусный литров BeerBack";
    else {
        $tmp = "";

        foreach ($cashback as $key => $value)
            $tmp .= sprintf("#%s %s начислено %s литров пива, чек: %s\n ", ($key+1), $value->created_at, $value->amount, $value->bill_number);

        $message = sprintf("*Статистика 20 последних начислений BeerBack*\n%s", $tmp);

    }


    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);
});
$botman->hears('/beerback_down', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();


    $user = User::where("telegram_chat_id", $id)->first();

    if (is_null($user))
        $user = createUser($bot);

    $cashback = \App\CashBackHistory::where("user_id", $user->id)
        ->where("type", 1)
        ->orderBy("id", "desc")
        ->take(20)
        ->skip(0)
        ->get();

    if (count($cashback) == 0)
        $message = "На текущий момент у вас нет списаний бонусных литров BeerBack";
    else {
        $tmp = "";

        foreach ($cashback as $key => $value)
            $tmp .= sprintf("#%s %s списано %s литров пива\n ", ($key+1), $value->created_at, $value->amount);

        $message = sprintf("*Статистика 20 последних списаний BeerBack*\n%s", $tmp);

    }

    $keyboard = [
        [
            ['text' => "Получено", 'callback_data' => "/beerback_up"],
        ],
    ];

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);
});

