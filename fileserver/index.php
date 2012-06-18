<?php
	include_once('config/cfg.php');

	header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
	header("Access-Control-Allow-Headers: *");

	if (!empty($_FILES))
	{
		/**
		 * приходит массив с информацией о файле array(1) {
		 * ["Filedata"]=>
					array(5) {
					["name"]=>string(43) "design.mycloud.ver1_4_enter_more-clouds.jpg"
					["type"]=>string(10) "image/jpeg"
					["tmp_name"]=>string(14) "/tmp/phpikgVJm"
					["error"]=>int(0)
					["size"]=>int(0)
					}
				}
		 */
		if (!empty($_POST))
		{
			/**
			 * ДОП. ПАРАМЕТРЫ
			 * array(
			 * 		"key"	=>string,
			 * 		"userid"=>int
			 * 		"params"=> array - доп. параметры для типизации файла
			 * )
			 */

			$result = '';
			if (!empty($_POST["key"]) && !empty($_POST['userid']))
			{
				$uid = $_POST["userid"];
				//СОХРАНЯЕМ ФАЙЛЫ
				$filePath = $uid;//ПУТЬ СОХРАНЕНИЯ ФАЙЛА НА СЕРВЕРЕ
				$path = $_SERVER['DOCUMENT_ROOT'] . '/upl/' . $filePath . '/';
				if (!file_exists($path))
				{
					mkdir($path, 0755);
				}
				$info = pathinfo($_FILES["Filedata"]["name"]);
				$fileName = md5($_FILES["Filedata"]["tmp_name"]) . '.' . strtolower($info['extension']);//ИМЯ ФАЙЛА НА СЕРВЕРЕ
				$fullName = $path . $fileName;

				$saveSuccess = move_uploaded_file($_FILES["Filedata"]["tmp_name"], $fullName);

				$serverIp = '127.0.0.1'; //IP ДАННОГО ФАЙЛОВОГО СЕРВЕРА
				$serverId = _SERVER_ID_; //ИДЕНТИФИКАТОР ДАННОГО ФАЙЛОВОГО СЕРВЕРА
				//ЗАПОЛНЯЕМ СТРУКТУРУ, ОПИСЫВАЮЩУЮ ФАЙЛ
				if (!empty($saveSuccess))
				{
					$fileMD5 = md5_file($fullName);//MD5 ФАЙЛА
					$fileSize = filesize($fullName);
					$fileInfo = array(
						"file_original" => $_FILES["Filedata"]["name"],
						"file_name" => $fileName,
						"file_path" => $filePath,//ЦЕЛОЕ ЧИСЛО
						"file_MD5" => $fileMD5,
						"file_size" => $fileSize,
						"server_ip" => $serverIp,
					);

					$key = $_POST["key"];
					$sfile = serialize($fileInfo);
					$sparams = serialize(array());
					if (!empty($_POST['params']))
						$sparams = serialize($_POST['params']);
					$sum = sha1($sfile . $serverId);

					//ЗАПРОС ЧЕРЕЗ CURL
					//*
					$ch = curl_init(_MYCLOUD_);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
					curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));//ОБХОДИМ ПРОБЛЕМУ С NGINX
					$data = 'key=' . $key . '&uid=' . $uid . '&sum=' . $sum . '&sid=' . $serverId . '&sfile=' . $sfile . '&sparams=' . $sparams;
					curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
					curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 0);
					$result = curl_exec($ch);
					curl_close($ch);
					//*/
				}
			}

			if ($result <> 'ok')
			{
				//ДАННЫЕ НЕ ПРОШЛИ ПРОВЕРКУ, ФАЙЛЫ НУЖНО УДАЛИТЬ
				unlink($fullName);
			}
			echo $result;
		}
	}