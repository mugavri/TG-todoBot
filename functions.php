<?php

function telegram($method, $datas = [], $API_KEY = API_TOKEN, $keyboardeader = null)
{
    $url = "https://api.telegram.org/bot" . $API_KEY . "/" . $method;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);

    if ($keyboardeader != null) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $keyboardeader);
    }

    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
        curl_close($ch);
        exit(1);
    } else {
        curl_close($ch);
        return json_decode($res, true);
    }
}

function getUpdateInfo($update)
{
    if (isset($update['message'])) {
        return [
            'id' => $update['message']['from']['id'],
            'text' => isset($update['message']['text']) ? $update['message']['text'] : null,
            'from' => $update['message']['from'],
            'chat' => $update['message']['chat'],
            'caption' => isset($update['message']['caption']) ? $update['message']['caption'] : null
        ];
    } else if (isset($update['edited_message'])) {
        return [
            'id' => $update['edited_message']['from']['id'],
            'text' => isset($update['edited_message']['text']) ? $update['edited_message']['text'] : null,
            'from' => $update['edited_message']['from'],
            'chat' => $update['edited_message']['chat'],
            'caption' => isset($update['edited_message']['caption']) ? $update['edited_message']['caption'] : null
        ];
    } else if (isset($update['callback_query'])) {
        return [
            'id' => $update['callback_query']['from']['id'],
            'text' => $update['callback_query']['message']['text'],
            'query' => $update['callback_query']['data'],
            'from' => $update['callback_query']['from'],
            'chat' => isset($update['callback_query']['chat']) ? $update['callback_query']['chat'] : null,
            'caption' => isset($update['callback_query']['caption']) ? $update['callback_query']['caption'] : null
        ];
    } else if (isset($update['inline_query'])) {
        return [
            'id' => $update['inline_query']['from']['id'],
            'text' => $update['inline_query']['query'],
            'query' => $update['inline_query']['query'],
            'from' => $update['inline_query']['from'],
            'caption' => $update['inline_query']['caption']
        ];
    } else if (isset($update['channel_post'])) {
        return [
            'id' => $update['channel_post']['chat']['id'],
            'text' => isset($update['channel_post']['text']) ? $update['channel_post']['text'] : null,
            'from' => $update['channel_post']['chat'],
            'chat' => $update['channel_post']['chat'],
            'caption' => isset($update['channel_post']['caption']) ? $update['channel_post']['caption'] : null
        ];
    }
}

function _log($t = "log_here", $obj = null, $debug_backtrace = false)
{
    if ($debug_backtrace) {
        _log(debug_backtrace(), null, false);
    }

    if ($obj != null && is_string($t)) {
        if (!is_string($obj)) {
            $obj = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        $t = $t . ":\n" . $obj;
    }

    if (!is_string($t)) {
        $t = json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    usleep(25000);

    $texts = mb_str_split($t, 4096);

    foreach ($texts as $text) {

        $postData = array(
            'chat_id' => MY_TG_ID,
            'text' => $text
        );

        $a = telegram('sendMessage', $postData);
    }

    return $a;
}

function getData($file_name = 'data.json')
{
    return json_decode(file_get_contents($file_name), true);
}

function saveData($data = '{}', $file_name = 'data.json')
{
    $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    file_put_contents($file_name, $data);

    return $data;
}


function getTodosInlineKeyboard($todos, $done = false, $offset = 0, $rows_number = 10)
{
    $callback_name = $done ? 'todos 1' : 'todos 0';
    $keyboard = array();

    $todos_slice = array_slice($todos, $offset, $rows_number);

    foreach ($todos_slice as $id => $todo) {

        // array_push($keyboard, array(
        //     array(
        //         'callback_data' => "delete_todo " . $id,
        //         'text' => '🗑'
        //     ),
        //     array(
        //         'callback_data' => "view_todo " . $id,
        //         'text' => '👁'
        //     ),
        //     array(
        //         'callback_data' => "check_todo " . $id,
        //         'text' => '✅'
        //     )
        // ));

        array_push($keyboard, array(
            array(
                'callback_data' => "view_todo " . $todo['id'],
                'text' => '👁 צפה במטלה ' . $todo['id']
            )
        ));
    }

    $count_todos = count($todos);
    $end = $offset + $rows_number;

    if ($end > $count_todos) {
        $end = $count_todos;
    }

    if ($offset > 0 && $end < $count_todos) {
        array_push(
            $keyboard,
            array(
                array(
                    'callback_data' => $callback_name . ' ' . ($offset - $rows_number),
                    'text' => "הקודם >>>"
                ),
                array(
                    'callback_data' => $callback_name . ' ' . $end,
                    'text' => "<<< הבא"
                )
            )
        );
    } else if ($end < $count_todos) {
        array_push(
            $keyboard,
            array(array(
                'callback_data' => $callback_name . ' ' . $end,
                'text' => "<<< הבא"
            ))
        );
    } else if ($offset > 0) {
        array_push(
            $keyboard,
            array(array(
                'callback_data' => $callback_name . ' ' . ($offset - $rows_number),
                'text' => "הקודם >>>"
            ))
        );
    }

    array_push(
        $keyboard,
        array(array(
            'callback_data' => 'menu',
            'text' => "תפריט ראשי"
        ))
    );

    return json_encode(array(
        'inline_keyboard' => $keyboard
    ));
}

function getTodosText($todos, $title)
{
    $text = "<b>" . $title . "</b>\n\n";

    foreach ($todos as $id => $todo) {
        $show_dots = (count($todo['text']) > 30);
        $text .= $id . '. ' . substr($todo['text'], 0, 30) . ($show_dots ? '' : '...') . "\n";
    }

    return $text;
}

function getDefaultKeyboard()
{
    return json_encode(array(
        'inline_keyboard' => array(
            array(array(
                'callback_data' => "todos 0 0",
                'text' => 'מטלות לעשות'
            )),
            array(array(
                'callback_data' => "todos 1 0",
                'text' => 'מטלות שבוצעו'
            )),
            array(array(
                'callback_data' => "add_todo",
                'text' => "הוספת מטלה"
            ))
        )
    ));
}

function filterTodos($todos, $done)
{
    return array_filter($todos, function ($todo) use ($done) {
        return $todo['is_done'] === $done;
    });
}


function getTodosForRemove($todos, $id)
{
    return array_filter($todos, function ($todo) use ($id) {
        return $todo['id'] !== $id;
    });
}
