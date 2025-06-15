<?php

// Устанавливаем кодировку UTF-8
header('Content-Type: text/html; charset=UTF-8');

// Путь к файлу для хранения данных
$lockFile = __DIR__ . '/base/base.txt';

// Регулярное выражение для проверки UUID
$uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

// Функция проверки времени простоя
function checkIdleTime($lastPingTime) {
    // Получаем текущее время
    $currentTime = time();
    
    // Проверяем время простоя
    if ($lastPingTime === null || ($currentTime - $lastPingTime) > 60) {
        return false;
    }
    return true;
}

// Функция для обработки /ping
function handlePing($uuid) {
    global $lockFile, $uuidRegex; // Добавляем глобальные переменные
    
    // Проверяем формат UUID
    if (!preg_match($uuidRegex, $uuid)) {
        http_response_code(400);
        echo "Неверный формат UUID";
        return;
    }
    
    // Получаем текущее время
    $currentTime = time();
    
    // Создаем директорию, если не существует
    $dir = dirname($lockFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Читаем существующие данные
    $data = file_exists($lockFile) ? json_decode(file_get_contents($lockFile), true) : [];
    
    // Добавляем новый UUID
    $data['uuids'][$uuid] = [
        'time' => $currentTime
    ];
    
    // Записываем данные в файл
    if (!file_put_contents($lockFile, json_encode($data))) {
        http_response_code(500);
        echo "Ошибка записи в файл";
        return;
    }
    
    // Возвращаем успешный ответ
    http_response_code(200);
    echo "Pong";
}

// Функция для обработки проверки статуса
function handleCheck($uuid) {
    global $lockFile, $uuidRegex; // Добавляем глобальные переменные
    
    // Проверяем формат UUID
    if (!preg_match($uuidRegex, $uuid)) {
        http_response_code(400);
        echo "Неверный формат UUID";
        return;
    }
    
    // Читаем данные из файла
    if (file_exists($lockFile)) {
        $data = json_decode(file_get_contents($lockFile), true);
        
        // Проверяем существование UUID
        if (!isset($data['uuids'][$uuid])) {
            http_response_code(404);
            echo "UUID не найден";
            return;
        }
        
        // Проверяем время простоя
        if (checkIdleTime($data['uuids'][$uuid]['time'])) {
            http_response_code(200);
            echo "Сервис активен";
        } else {
            http_response_code(500);
            echo "Сервис неактивен более минуты";
        }
    } else {
        http_response_code(404);
        echo "UUID не найден";
    }
}

// Парсим UUID из URL
$uri = $_SERVER['REQUEST_URI'];
$parts = explode('/', $uri);
$uuid = isset($parts[3]) ? $parts[3] : null;

// Обработчик запросов
if ($uri === '/ping' || $uri === '/check') {
    http_response_code(404);
    echo "Not found UUID";
} elseif ($uri === '/ping/' . $uuid) {
    handlePing($uuid);
} elseif ($uri === '/check/' . $uuid) {
    handleCheck($uuid);
} else {
    http_response_code(404);
    echo "Неверный URL";
}