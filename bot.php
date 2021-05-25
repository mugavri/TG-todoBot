<?php

// set setting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// imports files
require_once 'setting.php';
require_once 'functions.php';

// getting updates from telegram
$update = json_decode(file_get_contents('php://input'), true);


// setting vars
$info = getUpdateInfo($update);
$user_id = $info['id'];
$text = $info['text'];
$data = getData();



// add new user to db
if (!isset($data['users'][$user_id])) {
    $data['users'][$user_id] = array_merge(
        $info['from'],
        [
            'start_date' => time(),
            'last_used' => time(),
            'last_todo_id' => 1,
            'todos' => []
        ]
    );

    $data = saveData($data);
}

if ($text == '/start') {
    $name = $info['from']['first_name'];

    $keyboard = getDefaultKeyboard();

    $postData = array(
        'chat_id' => $user_id,
        'text' => "שלום $name ברוכים הבאים לרובוט\n\n אני בוט ששומר את המטלות שלכם",
        'parse_mode' => 'HTML',
        'reply_markup' => $keyboard
    );

    telegram('sendMessage', $postData);
} else if (isset($info['query'])) {
    $query = $info['query'];

    $postData = array(
        'callback_query_id' => $update["callback_query"]['id'],
    );

    telegram('answerCallbackQuery', $postData);


    if ($query == 'add_todo') {
        $postData = array(
            'chat_id' => $user_id,
            'message_id' => $update["callback_query"]["message"]["message_id"],
            'text' => "שלחו לי את המטלה להוספה",
        );

        telegram('editMessageText', $postData);
    } else if ($query == 'menu') {

        $keyboard = getDefaultKeyboard();


        $postData = array(
            'chat_id' => $user_id,
            'message_id' => $update["callback_query"]["message"]["message_id"],
            'text' => "שלום $name ברוכים הבאים לרובוט\n\n אני בוט ששומר את המטלות שלכם",
            'reply_markup' => $keyboard

        );

        telegram('editMessageText', $postData);
    } else if (preg_match('/(^todos)/', $query)) {
        // _log('error', error_get_last(), true);

        $done = (explode(" ", $query)[1] === '0' ? false : true);
        $offset = explode(" ", $query)[2];

        $todos = $data['users'][$user_id]['todos'];

        $filterd_todos = filterTodos($todos, $done);
        $title = $done ? 'מטלות שבוצעו' : 'רשימת מטלות לעשות';

        $keyboard = getTodosInlineKeyboard($filterd_todos, $done, $offset);
        $text = getTodosText($filterd_todos, $title);

        $postData = array(
            'chat_id' => $user_id,
            'message_id' => $update["callback_query"]["message"]["message_id"],
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard

        );

        telegram('editMessageText', $postData);
    } else if (preg_match('/(^delete_todo)/', $query)) {
        $todo_id = explode(" ", $query)[1];

        unset($data['users'][$user_id]['todos'][$todo_id]);

        $keyboard = getDefaultKeyboard();

        $postData = array(
            'chat_id' => $user_id,
            'message_id' => $update["callback_query"]["message"]["message_id"],
            'text' => "המטלה נמחקה",
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard
        );

        telegram('editMessageText', $postData);
    } else if (preg_match('/(^check_todo)/', $query)) {
        $todo_id = explode(" ", $query)[1];

        $todo = $data['users'][$user_id]['todos'][$todo_id];

        $done = (explode(" ", $query)[2] === '0' ? false : true);

        $date = date('d/m/Y H:i:s', $todo['add_date']);

        $text = "סטטוס שונה בהצלחה\n\nמטלה מספר $todo_id <i>($date)</i>\n\n";
        $text .= $todo['text'];

        $data['users'][$user_id]['todos'][$todo_id]['is_done'] = $done;

        $keyboard = json_encode(array(
            'inline_keyboard' => array(
                array(array(
                    'callback_data' => "delete_todo " . $todo_id,
                    'text' => '🗑 מחק מטלה'
                )),
                array(array(
                    'callback_data' => "check_todo " . $todo_id . ' ' .  $todo['is_done'],
                    'text' => ($todo['is_done'] === false ? "✅ בטל סימון" : "✅ סמן כבוצע")
                )),
                array(array(
                    'callback_data' => "menu",
                    'text' => "תפריט ראשי"
                ))
            )
        ));


        $postData = array(
            'chat_id' => $user_id,
            'message_id' => $update["callback_query"]["message"]["message_id"],
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard
        );

        telegram('editMessageText', $postData);
    } else if (preg_match('/(^view_todo)/', $query)) {
        $todo_id = explode(" ", $query)[1];

        $todo = $data['users'][$user_id]['todos'][$todo_id];

        $date = date('d/m/Y H:i:s', $todo['add_date']);

        $text = "מטלה מספר $todo_id <i>($date)</i>\n\n";
        $text .= $todo['text'];

        $keyboard = json_encode(array(
            'inline_keyboard' => array(
                array(array(
                    'callback_data' => "delete_todo " . $todo_id,
                    'text' => '🗑 מחק מטלה'
                )),
                array(array(
                    'callback_data' => "check_todo " . $todo_id . ' ' .  $todo['is_done'],
                    'text' => ($todo['is_done'] === true ? "✅ בטל סימון" : "✅ סמן כבוצע")
                )),
                array(array(
                    'callback_data' => "menu",
                    'text' => "תפריט ראשי"
                ))
            )
        ));

        $postData = array(
            'chat_id' => $user_id,
            'message_id' => $update["callback_query"]["message"]["message_id"],
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard

        );

        telegram('editMessageText', $postData);
    }
} else if (isset($text)) {

    $last_todo_id = $data['users'][$user_id]['last_todo_id'];
    $data['users'][$user_id]['last_todo_id']++;

    $data['users'][$user_id]['todos'][$last_todo_id] = [
        'id' => $last_todo_id,
        'text' => $text,
        'is_done' => false,
        'add_date' => time()
    ];
    saveData($data);

    $keyboard = getDefaultKeyboard();

    $postData = array(
        'chat_id' => $user_id,
        'text' => "מעולה, הוספתי את המטלה.",
        'parse_mode' => 'HTML',
        'reply_markup' => $keyboard
    );

    telegram('sendMessage', $postData);
} else {
    $keyboard = getDefaultKeyboard();

    $postData = array(
        'chat_id' => $user_id,
        'text' => "ניתן לשלוח רק טקסט כמטלה",
        'parse_mode' => 'HTML',
        'reply_markup' => $keyboard
    );

    telegram('sendMessage', $postData);
}




// update last_used
$data['users'][$user_id]['last_used'] = time();
saveData($data);
