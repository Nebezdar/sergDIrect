<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['csvfile']['tmp_name'];
        $directCsvFile = 'direct.csv';

        if (!file_exists($directCsvFile)) {
            echo "Файл direct.csv не найден.";
            exit;
        }

        try {
// Чтение загруженного файла и сбор cm_id
            $uploadedData = [];
            $cmIds = [];

            if (($handle = fopen($uploadedFile, 'r')) !== FALSE) {
                $header = fgetcsv($handle);
                while (($data = fgetcsv($handle)) !== FALSE) {
// Проверяем, есть ли как минимум 2 столбца в строке
                    if (isset($data[1])) {
                        $httpReferer = $data[1];
                        if (is_string($httpReferer) && preg_match('/cm_id=(\d+)/', $httpReferer, $matches)) {
                            $cm_id = $matches[1];
                            $cmIds[$cm_id] = true; // Сохраняем только уникальные cm_id
                            $uploadedData[$cm_id][] = $data; // Сохраняем строку по ключу cm_id
                        }
                    }
                }
                fclose($handle);
            }

// Создание нового CSV файла с совпадающими строками
            $outputFile = 'response_' . time() . '.csv';
            $newFileHandle = fopen($outputFile, 'w');
            fputcsv($newFileHandle, array_merge($header, ['Доп. данные из direct.csv']));

// Чтение direct.csv построчно и поиск совпадений
            if (($handle = fopen($directCsvFile, 'r')) !== FALSE) {
                while (($rowData = fgetcsv($handle)) !== FALSE) {
// Проверяем, есть ли как минимум 10 столбцов в строке
                    if (isset($rowData[9])) {
                        $directId = $rowData[9]; // Используем 10-й столбец для поиска
                        if (isset($cmIds[$directId])) {
                            foreach ($uploadedData[$directId] as $data) {
                                fputcsv($newFileHandle, array_merge($data, $rowData));
                            }
                        }
                    }
                }
                fclose($handle);
            }

            fclose($newFileHandle);

// Отправка файла пользователю
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment;filename="' . $outputFile . '"');
            header('Cache-Control: max-age=0');
            readfile($outputFile);

// Удаление временного файла
            unlink($outputFile);
            exit;

        } catch (Exception $e) {
            echo 'Ошибка обработки файла: ' . $e->getMessage();
        }
    } else {
        echo "Ошибка загрузки файла.";
    }
}
