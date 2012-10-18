<?php

if (!defined("_PARTNER_"))
{
	die("Error. Not executable. (Try [_partner_name_].php)\n\n");
}

/**
 * @protected PartnerTransport $transport
 */

class cConverter
{
	/**
	 * имя лог-файла текушей сессии
	 *
	 * @var string
	 */
	public $logFileName;

	/**
	 * имя файла кода ошибки последней операции
	 *
	 * @var string
	 */
	public $crashFileName;

	/**
	 * Код ошибки при выполнении операции
	 *
	 * @var integer
	 */
	public $errorNo;

	/**
	 * Сообщение об ошибке
	 *
	 * @var string
	 */
	public $errorMsg;

	/**
	 * ВЫПОЛННИЕ КОМАНДЫ ОЧЕРЕДИ (magic)
	 *
	 * @param mixed $cmdInfo - структура данных команды
	 */
	public function run($cmdInfo)
	{
		$this->threadCount--; //ЗАНИМАЕМ ПОТОК
		$info = array();
		if (!empty($cmdInfo['info']))
			$info = unserialize($cmdInfo['info']);
		else
			$cmdInfo['info'] = serialize($info);

		$this->log('выполняется очередь ' . $cmdInfo['id'] . ' действие ' . $cmdInfo['cmd_id'] . ' (состояние ' . $cmdInfo['state'] . ') id объекта=' . $cmdInfo['original_id'] . ' партнера ' . _PARTNER_);
		if (!empty($cmdInfo['state']))
		{
			if ($this->operationIsFailed())
			{
/*
ПЕРЕД НАЧАЛОМ ОПЕРАЦИИ (state=0)
ПРОВЕРКУ НАЛИЧИЯ ФАЙЛА ОШИБОК (.errors) НЕ ДЕЛАЕМ
ФАЙЛ ОШИБОК МОГ ОСТАТЬСЯ ОТ ПРОШЛОЙ ОПЕРАЦИИ, ЗАВЕРШИВШЕЙСЯ ОШИБКОЙ
И ПРИ СТАРТЕ С ПРОМЕЖУТОЧНОЙ ОПЕРАЦИИ ТАКАЯ ПРОВЕРКА НЕ НУЖНА
*/
				$this->setQueueState($cmdInfo, _STATE_ERR_);
				$this->threadCount++;
				return;
			}
		}
		if (!$this->initBat($cmdInfo))
		{
			$this->log('невозможно создать командный файл ' . $this->batName);
			//die(iconv(_SOURCE_CHARSET_, _CONSOLE_CHARSET_, 'Error. Невозможно создать командный файл ' . $this->batName));
			return;
		}

		if (!empty($info['files']) || ($cmdInfo['cmd_id'] == _CMD_TODO_))
		{
			if ($cmdInfo['cmd_id'] == _CMD_TODO_)
			{
				if (_PARTNER_ID_ > 0) //-1 ДЛЯ СОБСТВЕННЫХ ВИТРИН; 0 - ДЛЯ ФАЙЛОВ ПОЛЬЗОВАТЕЛЕЙ
				{
					//ОБЪЕКТ ПОСТАВЛЕН В ОЧЕРЕДЬ С САЙТА ПАРТНЕРА
					//ПРОВЕРЯЕМ ЕСТЬ ЛИ ОН УЖЕ В ОЧЕРЕДИ ОТ ДРУГОГО ПОЛЬЗОВАТЕЛЯ И ЗАПУЩЕН
					$sql = 'SELECT id FROM dm_income_queue WHERE
						id <> ' . $cmdInfo['id'] . ' AND original_id = ' . $cmdInfo['original_id'] . '
						AND partner_id = ' . _PARTNER_ID_ . ' AND cmd_id > ' . _CMD_TODO_ . '	LIMIT 1
					';
					$this->db = $this->connectDb("mycloud", $this->db);
					$r = mysql_query($sql, $this->db);
					$cmdExists = mysql_fetch_assoc($r);
					mysql_free_result($r);
					if ($cmdExists)
					{
						$this->log('объект уже добавлен в очередь другим пользователем');
						//ПОДНИМЕМ ПРИОРИТЕТ СУЩЕСТВУЮЩЕГО ЗАДАНИЯ
						$sql = 'UPDATE dm_income_queue SET priority = priority + ' . $cmdInfo['priority'] . ' + 1 WHERE id = ' . $cmdExists['id'];
						mysql_query($sql, $this->db);
						//У НОВОГО ЗАДАНИЯ ПОНИЖАЕМ ПРИОРИТЕТ. ОНО ВСЕ РАВНО БУДЕТ ВЫПОЛНЕНО ВНЕ ОЧЕРЕДИ СРАЗУ ПО ЗАВЕРШЕНИЮ СУЩЕСТВУЮЩЕГО ЗАДАНИЯ
						$sql = 'UPDATE dm_income_queue SET priority = 0, station_id = ' . _STATION_ . ' WHERE id = ' . $cmdInfo['id'];
						mysql_query($sql, $this->db);
						//ОСВОБОЖДАЕМ ПОТОК И ПЕРЕХОДИМ К СЛЕДУЮЩЕМУ ОБЪЕКТУ
						$this->threadCount++;
						return;
					}
				}

				//ПРОВЕРЯЕМ ЕСТЬ ЛИ ОН УЖЕ В ВИТРИНАХ ПАРТНЕРА
				//КОНТЕНТ ФАЙЛОВЫХ СЕРВЕРОВ ОБЛАКА ОБРАБАТЫВАЕТСЯ ПОД partner_id = 0
				//КОНТЕНТ ВИТРИН ОБЛАКА ОБРАБАТЫВАЕТСЯ ПОД partner_id = -1
				$productExists = array();
				if (($cmdInfo['partner_id']) > 0)
				{
					if ($cmdInfo['original_variant_id'] > 0)
						$sql = 'SELECT p.id FROM dm_products AS p INNER JOIN dm_product_variants AS pv ON (pv.product_id = p.id)
							WHERE p.id = ' . $cmdInfo['original_id'] . ' AND pv.original_id = ' . $cmdInfo['original_variant_id'] . '
							AND p.partner_id = ' . _PARTNER_ID_ . ' LIMIT 1
						';
					else
						$sql = 'SELECT p.id FROM dm_products AS p WHERE p.original_id = ' . $cmdInfo['original_id'] . '
							AND p.partner_id = ' . _PARTNER_ID_ . ' LIMIT 1
						';
					$r = mysql_query($sql, $this->db);
					$productExists = mysql_fetch_assoc($r);
					mysql_free_result($r);
				}

				if (!empty($productExists))
				{
					//ПРОДУКТ УЖЕ В ВИТРИНАХ, ПЕРЕХОДИМ К ОПЕРАЦИИ ДОБАВЛЕНИЯ В ПП
					$cmdInfo['cmd_id'] = _CMD_UNIVERSE_;
					$this->setQueueCmd($cmdInfo, $cmdInfo['cmd_id']);
					$this->setQueueState($cmdInfo, _STATE_WAIT_);
				}
				else
				{
					//ВЫЧИТЫВАЕМ С ПАРТНЕРА ЧЕРЕЗ ТРАНСПОРТ ИНФУ О ВСЕХ ВАРИАНТАХ И ЗАКРЕПЛЯЕМ ОБЪЕКТ ЗА СОБОЙ
					$this->log('получаем через транспорт партнера ' . _PARTNER_ . ' инфу об объекте ' . $cmdInfo['original_id'] . ' очереди ' . $cmdInfo['id']);
					$queue = $this->transport->getObjectToQueue($cmdInfo['original_id'], $cmdInfo['original_variant_id']);

					foreach ($queue as $q)
					{
						$info = array(
							'just_online' => $q['just_online'],
							'files' => $q['files'],
							'md5s' => $q['md5s'],
							'ovids' => $q['ovids'],
							'tags' => $q['tags'],
							'group_id' => (empty($q['group_id'])) ? 0 : $q['group_id'],
						);

						$qInfo = array(
							'cmd_id'		=> _CMD_COPY_, //КОПИРОВАНИЕ - ПЕРВОЕ ДЕЙСТВИЕ НАД ОБЪЕКТОМ ОЧЕРЕДИ
							'state'			=> _STATE_WAIT_,
							'station_id'	=> _STATION_,
							'info'			=> serialize($info),
						);
						$this->setQueueCmd($cmdInfo, $qInfo['cmd_id']);
						$this->setQueueState($cmdInfo, _STATE_WAIT_);
						$this->setQueueInfo($cmdInfo, $info);
						$sql = 'UPDATE dm_income_queue SET station_id = ' . _STATION_ . ' WHERE id = ' . $cmdInfo['id'];
						mysql_query($sql, $this->db);
					}
				}
			}

			switch ($cmdInfo['cmd_id'])
			{
				case _CMD_COPY_:
					switch ($cmdInfo['state'])
					{
						case _STATE_WAIT_:
							$checkConn = $this->transport->checkConnections();
							if ($checkConn)
							{
								//ЕСТЬ ОШИБКИ
								$this->threadCount++;
								$this->log($checkConn);
								break;
							}

							//ПРОЦЕСС НАЧАЛСЯ. СТАВИМ ДАТУ НАЧАЛА
							$sql = 'UPDATE dm_income_queue SET date_start = "' . date('Y-m-d H:i:s') . '" WHERE id = ' . $cmdInfo['id'];
							mysql_query($sql, $this->db);

							$cmdFiles = $this->transport->copyFiles($info['files']);
							$cmdPosters = $this->transport->copyPosters($info['tags']);
							$cmds = array();
							if (!empty($cmdFiles)) foreach ($cmdFiles as $c)
							{
								$cmds[] = $c;
							}
							if (!empty($cmdPosters)) foreach ($cmdPosters as $c)
							{
								$cmds[] = $c;
							}
							if (!empty($cmds))
							{
								$this->setQueueState($cmdInfo, _STATE_PROCESS_);//ИЗМЕНЯЕМ СОСТОЯНИЕ ОПЕРАЦИИ
								foreach ($info['files'] as $f)
								{
									$fInfo = pathinfo($f);
									$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
									$this->createTree(_COPY_PATH_ . $path);
								}

								if (!empty($info['tags']['poster']))
								{
									$fInfo = pathinfo($info['tags']['poster']);
									$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
									$this->createTree(_POSTER_PATH_ . $path);
								}

								foreach ($cmds as $c)
								{
									$this->cmd($cmdInfo['id'], $c, false);
								}
								$this->cmd($cmdInfo['id']);	//СОЗДАНИЕ ФАЙЛА ЗАВЕРШЕНИЯ ОПЕРАЦИИ
							}
							else
							{
								$this->log('список команд на копирование пуст');
								$this->setQueueState($cmdInfo, _STATE_ERR_);
								$this->threadCount++;
								return;
							}
						break;
						case _STATE_PROCESS_:
							if (!$this->operationIsComplete())
							{
								break;
							}

							$this->setQueueCmd($cmdInfo, _CMD_CONV_);
							$this->setQueueState($cmdInfo, _STATE_WAIT_);
						break;
					}
				break;

				case _CMD_CONV_:
					switch ($cmdInfo['state'])
					{
						case _STATE_WAIT_:
							$this->setQueueState($cmdInfo, _STATE_PROCESS_);//ИЗМЕНЯЕМ СОСТОЯНИЕ ОПЕРАЦИИ

							$newFiles = array();
							$filesPresets = array();//ХРАНИМ СПИСОК ПРЕСЕТОВ ДЛЯ КАЖДОГО НОВОГО ФАЙЛА
							foreach ($info['files'] as $f)
							{
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
								$f2 = str_replace("." . $fInfo['extension'], '.mp4', $fInfo['basename']);//ЗАМЕНИЛИ РАСШИРЕНИЕ
								//$f2 = $fInfo['filename'] . '.mp4';//ЗАМЕНИЛИ РАСШИРЕНИЕ
								$newFiles[] = $path . _SL_ . $f2;//У ФАЙЛОВ НОВЫЕ ИМЕНА

								$presets = $this->getPresetList($f);
								$filesPresets[] = $presets;
								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									if ($this->createTree(_CONV_PATH_ . $p2))
									{
										//УЗНАЕМ КОЛ-ВО ДОРОЖЕК
										$numTracks = array();
										exec('mediainfo --Inform="Audio;%ID%," ' . _COPY_PATH_ . $f, $numTracks);
										$numTracks = trim($numTracks[0]);
										if (!empty($numTracks))
										{
											$numTracks = count(explode(',', $numTracks));
											$this->log('найдено ' . $numTracks . ' дорожек во входном файле ' . _COPY_PATH_ . $f);
											if ($numTracks > 1) $numTracks--;
											if ($numTracks > _AUDIO_TRACKS_LIMIT_) $numTracks = _AUDIO_TRACKS_LIMIT_;
										}

										$tracksKeys = '';
										if (!empty($numTracks))
										{
											$param_a = range (1, $numTracks);
											$param_E = array_fill(0, $numTracks, 'faac');
											$param_B = array_fill(0, $numTracks, '160');
											$param_6 = array_fill(0, $numTracks, 'dpl2');
											$param_R = array_fill(0, $numTracks, 'Auto');
											$param_D = array_fill(0, $numTracks, '0.0');

											$tracksKeys = ' -a ' . implode(',', $param_a) .
												' -E ' . implode(',', $param_E) .
												' -B ' . implode(',', $param_B) .
												' -6 ' . implode(',', $param_6) .
												' -R ' . implode(',', $param_R) .
												' -D ' . implode(',', $param_D);
										}

										$this->cmd($cmdInfo['id'], "sh " . _CMD_PATH_ . _SL_ . "presets/{$preset}.sh " .
											_COPY_PATH_ . $f . " " .
											_CONV_PATH_ . $p2 . _SL_ . $f2 . " " .
											_LOG_PATH_ . ' "' . $tracksKeys . '"', false);
									}
								}
							}
							$newFiles = array('newfiles' => $newFiles, 'filepresets' => $filesPresets);
							$this->setQueueInfo($cmdInfo, $newFiles);

							$this->cmd($cmdInfo['id']);	//СОЗДАНИЕ ФАЙЛА ЗАВЕРШЕНИЯ ОПЕРАЦИИ
						break;
						case _STATE_PROCESS_:
							if (!$this->operationIsComplete())
							{
								break;
							}

							$this->setQueueCmd($cmdInfo, _CMD_MODIFY_);
							$this->setQueueState($cmdInfo, _STATE_WAIT_);
								//ОСТАНАВЛИВАЕМ ПОСЛЕ КОНВЕРТАЦИИ ДЛЯ ОТЛАДКИ (ЧТОБЫ СБЭКАПИТЬ РЕЗУЛЬТАТЫ КОНВЕРТАЦИИ)
								//$this->setQueueState($cmdInfo, _STATE_ERR_);
						break;
					}
				break;

				case _CMD_MODIFY_:
					switch ($cmdInfo['state'])
					{
						case _STATE_WAIT_:
							$this->setQueueState($cmdInfo, _STATE_PROCESS_);//ИЗМЕНЯЕМ СОСТОЯНИЕ ОПЕРАЦИИ
							for ($i = 0; $i < count($info['files']); $i++)
							{
								$f = $info['files'][$i];
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
								$f2 = basename($info['newfiles'][$i]);
								$presets = $this->getPresetList($f);

								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									$fullName = _CONV_PATH_ . $p2 . _SL_ . $f2;
									$fullSize = sprintf("%u", filesize($fullName)) / 1024;
									$howMatch = $fullSize / _MP4BOX_MAX_SIZE_;
									if ($howMatch > 1)
									{
										$howMatch = intval($howMatch) + 1;
										//$divSize = intval($fullSize / $howMatch + 1024);
										$divSize = intval($fullSize / $howMatch + 1024 * $howMatch);
										//$divSize = intval($fullSize / $howMatch) + 1;
										$this->log('генерируем команду разделения на ' . $divSize . 'кБ файла ' . $fullName);
										$this->cmd($cmdInfo['id'], _MP4BOX_ . " " . $fullName . " -split-size " . $divSize, false);
										$this->cmd($cmdInfo['id'], "rm  -f " . $fullName, false);
									}

									if (!empty($info['tags']))
									{
										if ($howMatch > 1)
										{
											for ($part = 1; $part <= $howMatch; $part++)
											{
												$fParted = $this->formatPartedFilename($f2, $part);
												$mp4boxKeys = $this->getTagsKeys($info['tags'], _CONV_PATH_ . $p2, $fParted);
												$this->cmd($cmdInfo['id'], _MP4TAGS_ . " " . $mp4boxKeys . " " . _CONV_PATH_ . $p2 . _SL_ . $fParted, false);

												$mp4artKeys = $this->getPosterKeys($info['tags'], _CONV_PATH_ . $p2, $fParted);
												if (!empty($mp4artKeys))
													$this->cmd($cmdInfo['id'], _MP4ART_ . " " . $mp4artKeys . " " . _CONV_PATH_ . $p2 . _SL_ . $fParted, false);
												$this->cmd($cmdInfo['id'], _MP4BOX_ . " -inter 500 " . _CONV_PATH_ . $p2 . _SL_ . $fParted, false);
											}
										}
										else
										{
											$mp4boxKeys = $this->getTagsKeys($info['tags'], _CONV_PATH_ . $p2, $f2);
											$this->cmd($cmdInfo['id'], _MP4TAGS_ . " " . $mp4boxKeys . " " . _CONV_PATH_ . $p2 . _SL_ . $f2, false);

											$mp4artKeys = $this->getPosterKeys($info['tags'], _CONV_PATH_ . $p2, $f2);
											if (!empty($mp4artKeys))
												$this->cmd($cmdInfo['id'], _MP4ART_ . " " . $mp4artKeys . " " . _CONV_PATH_ . $p2 . _SL_ . $f2, false);
											$this->cmd($cmdInfo['id'], _MP4BOX_ . " -inter 500 " . _CONV_PATH_ . $p2 . _SL_ . $f2, false);
										}
									}
								}
							}
							$this->cmd($cmdInfo['id']);	//СОЗДАНИЕ ФАЙЛА ЗАВЕРШЕНИЯ ОПЕРАЦИИ
						break;
						case _STATE_PROCESS_:
//ВАЛИДАЦИЯ ФАЙЛОВ
							if (!$this->operationIsComplete())
							{
								break;
							}

							for ($i = 0; $i < count($info['files']); $i++)
							{
								$f = $info['files'][$i];
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
								$f2 = basename($info['newfiles'][$i]);
								$presets = $this->getPresetList($f);

								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									$fullName = _CONV_PATH_ . $p2 . _SL_ . $f2;

									$fNames = array();
									if (file_exists($fullName))
									{
										$fNames[] = $f2;
									}
									else
									{
										$this->log('не найден файл ' . $fullName);
								//СКОНВЕРТИРОВАННЫЙ ФАЙЛ РАЗДЕЛЕН
										$part = 1;
										while (file_exists(_CONV_PATH_ . $p2 . _SL_ . $this->formatPartedFilename($f2, $part))) {
											$fNames[] = $this->formatPartedFilename($f2, $part);
											$part++;
										}
									}

									foreach ($fNames as $fName)
									{
								//НАДО ПРОВЕРИТЬ КАЖДУЮ ЧАСТЬ
										$fullName = _CONV_PATH_ . $p2 . _SL_ . $fName;
										$duration = array();
										$this->log('запрос продолжительности файла ' . $fullName);
										exec('mediainfo --Inform="General;%Duration%" ' . $fullName, $duration);
										if (!empty($duration))
										{
											$duration = intval($duration[0]);//ПРОДОЛЖИТЕЛЬНОСТЬ В МИЛИСЕКУНДАХ
											if ($duration > 60000)
											{
												$start = $duration - 60000;
											}
											else
											{
												$start = $duration / 2;
											}
											if (!empty($start))
											{
												$start = round($start/1000);
												$this->cmd($cmdInfo['id'], "sh " . _CMD_PATH_ . "/presets/piece.sh " . _CONV_PATH_ . $p2 . _SL_ . $fName . ".piece " .
													"http://127.0.0.1:81/" . _PARTNER_ . _SL_ . basename(_CONV_PATH_) . $p2 . _SL_ . $fName . " " .
													$start . " " .
													_LOG_PATH_ . " " .
													_LOG_PATH_, false);
											}
										}
									}
								}
							}
							$this->cmd($cmdInfo['id']);	//СОЗДАНИЕ ФАЙЛА ЗАВЕРШЕНИЯ ОПЕРАЦИИ

							$this->setQueueState($cmdInfo, _STATE_OK_);
						break;
						case _STATE_OK_:
							if (!$this->operationIsComplete())
							{
								break;
							}
							$isGood = true;
							for ($i = 0; $i < count($info['files']); $i++)
							{
								$f = $info['files'][$i];
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
								$f2 = basename($info['newfiles'][$i]);

								$presets = $this->getPresetList($f);

								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									$fullName = _CONV_PATH_ . $p2 . _SL_ . $f2;

									$fNames = array();
									if (file_exists($fullName))
									{
										$fNames[] = $f2;
									}
									else
									{
										$this->log('не найден файл ' . $fullName);
								//СКОНВЕРТИРОВАННЫЙ ФАЙЛ РАЗДЕЛЕН
										$part = 1;
										while (file_exists(_CONV_PATH_ . $p2 . _SL_ . $this->formatPartedFilename($f2, $part))) {
											$fNames[] = $this->formatPartedFilename($f2, $part);
											$part++;
										}
									}

									if (!empty($fNames)) foreach ($fNames as $fName)
									{
										$fullName = _CONV_PATH_ . $p2 . _SL_ . $fName;
										$pieceName = _CONV_PATH_ . $p2 . _SL_ . $fName . ".piece";
										if (file_exists($fullName) && file_exists($pieceName))
										{
											if ((filesize($pieceName) == 0) || (filesize($fullName) == filesize($pieceName)))
											{
												$isGood = false;
											}
											unlink($pieceName);
										}
										else
										{
											$isGood = false;
										}
									}
									else
										$isGood = false;
								}
							}
							if ($isGood)
							{
								//ПЕРЕХОДИМ К СЛЕДУЮЩЕЙ ОПЕРАЦИИ
								$this->setQueueState($cmdInfo, _STATE_WAIT_);
								$this->setQueueCmd($cmdInfo, _CMD_CLOUDUP_);
							}
							else
							{
								//ФАЙЛЫ НЕ ПРОШЛИ ПРОВЕРКУ
								$this->setQueueState($cmdInfo, _STATE_ERR_);
								$this->threadCount++;
								return;
							}
						break;
					}
				break;
				case _CMD_CLOUDUP_:
					if ($cmdInfo['product_id'])
					{
						switch ($cmdInfo['state'])
						{
							case _STATE_WAIT_:
								$this->setQueueState($cmdInfo, _STATE_PROCESS_);//ИЗМЕНЯЕМ СОСТОЯНИЕ ОПЕРАЦИИ
								for ($i = 0; $i < count($info['files']); $i++)
								{

								}
							break;
						}
					}
					//ПЕРЕХОДИМ К СЛЕДУЮЩЕЙ ОПЕРАЦИИ
					$this->setQueueState($cmdInfo, _STATE_WAIT_);
					$this->setQueueCmd($cmdInfo, _CMD_PARTNERUP_);
				break;
				case _CMD_PARTNERUP_:
//КОПИРОВАНИЕ ПРЕОБРАЗОВАННЫХ ФАЙЛОВ
					$checkConn = $this->transport->checkConnections();
					if ($checkConn)
					{
						$this->threadCount++;
						$this->log($checkConn);
						break;
					}
					switch ($cmdInfo['state'])
					{
						case _STATE_WAIT_:
							$this->setQueueState($cmdInfo, _STATE_PROCESS_);//ИЗМЕНЯЕМ СОСТОЯНИЕ ОПЕРАЦИИ
							$cmds = array();
							for ($i = 0; $i < count($info['files']); $i++)
							{
								$f = $info['files'][$i];
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
								$f2 = basename($info['newfiles'][$i]);

								$presets = $this->getPresetList($f);

								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									$fullName = _CONV_PATH_ . $p2 . _SL_ . $f2;

									$cmd = $this->transport->copyOutCmd($f, $fullName, $preset);
									if (!empty($cmd))
									{
										foreach ($cmd as $c)
											$cmds[] = $c;
									}

									if (!empty($this->transport->errorMsg))
									{
										$this->setQueueState($cmdInfo, _STATE_ERR_);
										$this->log($this->transport->errorMsg);
										$this->threadCount++;
										return;
									}
								}
							}
							if (!empty($cmds))
							{
								foreach ($cmds as $c)
									$this->cmd($cmdInfo['id'], $c, false);

								$this->cmd($cmdInfo['id']);
							}
							else
							{
								$this->setQueueState($cmdInfo, _STATE_ERR_);
								$this->log("Ошибка. список команд на обратное копирование пуст");
								$this->threadCount++;
								return;
							}

						break;
						case _STATE_PROCESS_:
							if (!$this->operationIsComplete())
							{
								break;
							}
							//ПРОВЕРЯЕМ, СКОПИРОВАНО УСПЕШНО?
							$this->setQueueState($cmdInfo, _STATE_WAIT_);
							$this->setQueueCmd($cmdInfo, _CMD_SAVEBACK_);
						break;
					}
				break;
				case _CMD_SAVEBACK_:
					$checkConn = $this->transport->checkConnections();
					if ($checkConn)
					{
						$this->threadCount++;
						$this->log($checkConn);
						break;
					}
					switch ($cmdInfo['state'])
					{
						case _STATE_WAIT_:
							$this->setQueueState($cmdInfo, _STATE_PROCESS_);
							for ($i = 0; $i < count($info['files']); $i++)
							{
								$f = $info['files'][$i];
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
								$f2 = basename($info['newfiles'][$i]);

								$presets = $this->getPresetList($f);

								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									$fullName = _CONV_PATH_ . $p2 . _SL_ . $f2;

									$fSize = filesize($fullName);
									$fMd5 = md5_file($fullName);

									$height = array();
									exec('mediainfo --Inform="Video;%Height%" ' . $fullName, $height);
									$width = array();
									exec('mediainfo --Inform="Video;%Width%" ' . $fullName, $width);
									$resolution = '';
									if (!empty($height[0]) && !empty($width[0]))
									{
										$resolution = $width[0] . 'x' . $height[0];
									}
									$fInfo = array('size' => $fSize, 'md5' => $fMd5, 'path' => $path, 'resolution' => $resolution);
									$this->transport->saveBack($cmdInfo['original_id'], basename($f), $f2, $preset, $fInfo);
									if (!empty($this->transport->errorMsg))
									{
										$this->setQueueState($cmdInfo, _STATE_ERR_);
										$this->log($this->transport->errorMsg);
										$this->threadCount++;
										return;
									}
									$this->transport->updateMedia1($cmdInfo['original_id'], basename($f), $f2, $preset, $fInfo);
									if (!empty($this->transport->errorMsg))
									{
										$this->setQueueState($cmdInfo, _STATE_ERR_);
										$this->log($this->transport->errorMsg);
										$this->threadCount++;
										return;
									}
									unlink($fullName);
								}
								$this->transport->dropOriginal($cmdInfo['original_id']);
							}
							//УДАЛЯЕМ СТАРЫЙ КОНТЕНТ НА КОМПРЕССОРЕ И У ПАРТНЕРА
							/**
							 * ! к реализации
							 */
						break;
						case _STATE_PROCESS_:
							//РЕЗУЛЬТАТ ВЫПОЛНЕНИЯ ПРЕДЫДУЩЕЙ ОПЕРАЦИИ МОЖНО НЕ ПРОВЕРЯТЬ

							//!!! РЕЛИЗОВАТЬ СБРОС КЭША У ПАРТНЕРА
							$this->transport->clearCache($cmdInfo);

							//ПЕРЕХОДИМ К СЛЕДУЮЩЕЙ ОПЕРАЦИИ
							$this->setQueueState($cmdInfo, _STATE_WAIT_);
							$this->setQueueCmd($cmdInfo, _CMD_ADD_);
						break;
					}
				break;
				case _CMD_ADD_:
					//ДОБАВЛЕНИЕ В ВИТРИНЫ
					for ($i = 0; $i < count($info['files']); $i++)
					{
						$oInfo = pathinfo($info["files"][$i]);
						$fInfo = pathinfo($info["newfiles"][$i]);
						$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША
						$f2 = $fInfo['basename'];

						$presets = $this->getPresetList($info["files"][$i]);

						$fullName = _COPY_PATH_ . $info["files"][$i];
						$readyName = _READY_PATH_ . $oInfo['dirname'] . _SL_ . $oInfo['basename'];
						$this->createTree(_READY_PATH_ . $oInfo['dirname']);
						rename($fullName, $readyName);

						if (method_exists($this->transport, 'unlinkOriginalFile'))
						{
							$this->transport->unlinkOriginalFile($info["files"][$i]);
						}
					}

					//ДОБАВЛЕНИЕ В ВИТРИНЫ И В ПП ПОЛЬЗОВАТЕЛЕЙ, ОЖИДАЮЩИХ ОЧЕРЕДИ НА ЭТОТ ПРОДУКТ
					if ($cmdInfo['user_id'] > 0)
					{
						$cmd = 'wget -O ' . _TMP_PATH_ . '/wget.tmp ' . _MYCLOUD_SITE_ . '/products/addfromqueue/' . $cmdInfo['id'];
						exec($cmd);
					}

					$this->setQueueState($cmdInfo, _STATE_WAIT_);
					$this->setQueueCmd($cmdInfo, _CMD_DONE_);
					$this->threadCount++;
				break;

				case _CMD_UNIVERSE_:
					//ОТДЕЛЬНОЕ ДЕЙСТВИЕ НА ДОБАЛЕНИЕ В ПП (ПОСЛЕ ПРОВЕРКИ, ЧТО ПРОДУКТ УЖЕ В ВИТРИНЕ)
					//ДОБАВЛЕНИЕ В ВИТРИНЫ И В ПП ПОЛЬЗОВАТЕЛЕЙ, ОЖИДАЮЩИХ ОЧЕРЕДИ НА ЭТОТ ПРОДУКТ
					$cmd = 'wget -O ' . _TMP_PATH_ . '/wget.tmp ' . _MYCLOUD_SITE_ . '/products/addfromqueue/' . $cmdInfo['id'];
					exec($cmd);
					$this->setQueueState($cmdInfo, _STATE_WAIT_);
					$this->setQueueCmd($cmdInfo, _CMD_DONE_);
					$this->threadCount++;
				break;

				case _CMD_MEDIA1_:
					//ОТДЕЛЬНОЕ ДЕЙСТВИЕ НА ОБНОВЛЕНИЕ ДАННЫХ В БД MEDIA1

					$checkConn = $this->transport->checkConnections();
					if ($checkConn)
					{
						$this->threadCount++;
						$this->log($checkConn);
						break;
					}

				if (defined("_MEDIA_PATH_"))
					switch ($cmdInfo['state'])
					{
						case _STATE_WAIT_:
							$this->setQueueState($cmdInfo, _STATE_PROCESS_);
							for ($i = 0; $i < count($info['files']); $i++)
							{
								$f = $info['files'][$i];
								$fInfo = pathinfo($f);
								$path = $fInfo['dirname'];//ДОЛЖЕН НАЧИНАТЬСЯ СО СЛЭША И БУКВЫ АЛФАВИТА
								$f2 = basename($info['newfiles'][$i]);

								$presets = $this->getPresetList($f);

								foreach ($presets as $preset)
								{
									$p2 = _SL_ . $preset . $path;
									$fullName = _MEDIA_PATH_ . $path . _SL_ . $preset . _SL_ . $f2;
									if (!file_exists($fullName))
									{
										$this->setQueueState($cmdInfo, _STATE_ERR_);
										$this->log('не найден файл в хранилище MEDIA1 ' . $fullName);
										$this->threadCount++;
										return;
									}

									$fSize = filesize($fullName);
									$fMd5 = md5_file($fullName);

									$height = array();
									exec('mediainfo --Inform="Video;%Height%" ' . $fullName, $height);
									$width = array();
									exec('mediainfo --Inform="Video;%Width%" ' . $fullName, $width);
									$resolution = '';
									if (!empty($height[0]) && !empty($width[0]))
									{
										$resolution = $width[0] . 'x' . $height[0];
									}
									$fInfo = array('size' => $fSize, 'md5' => $fMd5, 'path' => $path, 'resolution' => $resolution);
									$this->transport->updateMedia1($cmdInfo['original_id'], basename($f), $f2, $preset, $fInfo);
									if (!empty($this->transport->errorMsg))
									{
										$this->setQueueState($cmdInfo, _STATE_ERR_);
										$this->log($this->transport->errorMsg);
										$this->threadCount++;
										return;
									}
								}
							}
							//УДАЛЯЕМ СТАРЫЙ КОНТЕНТ НА КОМПРЕССОРЕ И У ПАРТНЕРА
							/**
							 * ! к реализации
							 */

						}
					$this->setQueueState($cmdInfo, _STATE_WAIT_);
					$this->setQueueCmd($cmdInfo, _CMD_DONE_);
					$this->threadCount++;
				break;
			}
		}
	}

	public function formatPartedFilename($f2, $part)
	{
		$partedInfo = pathInfo($f2);
		//$fParted = str_replace("." . $partedInfo['extension'], "_" . sprintf('%03d', $part) . "." . $partedInfo['extension'], $f2);
		$fParted = str_replace("." . $partedInfo['extension'], "_" . sprintf('%03d', $part) . "." . $partedInfo['extension'], $f2);
		return $fParted;
	}

	/**
	 * получить список пресетов, исходя из разрешения оригинального файла
	 *
	 * @param string $inFile - относительный путь к файлу в директории входящего контента
	 * @return mixed
	 */
	public function getPresetList($inFile)
	{
		$presets = $width = array();
		exec('mediainfo --Inform="Video;%Width%" ' . _COPY_PATH_ . $inFile, $width);
		$presets[] = "low";
		if (!empty($width))
		{
			$width = intval($width[0]);//ШИРИНА
			if ($width >= 600)
				$presets[] = "medium";
			if ($width >= 1100)
				$presets[] = "high";
			if ($width >= 1600)
				$presets[] = "ultra";

			$this->log('получен список пресетов (' . implode(', ', $presets) . ') для файла ' . _COPY_PATH_ . $inFile);
		}
		return $presets;
	}

	public function setQueueState($cmdInfo, $state)
	{
		$this->log('устанавливаем состояние ' . $state . ' для id очереди=' . $cmdInfo['id'] . ' команда ' . $cmdInfo['cmd_id']);
		$this->db = $this->connectDb("mycloud", $this->db);
		$sql = 'UPDATE dm_income_queue SET state = ' . $state . ' WHERE id = ' . $cmdInfo['id'];
		mysql_query($sql, $this->db);
	}

	public function setQueueInfo($cmdInfo, $newInfo)
	{
		$info = unserialize($cmdInfo['info']);
		$info = array_merge($info, $newInfo);

		$this->db = $this->connectDb("mycloud", $this->db);
		$sql = "UPDATE dm_income_queue SET info = '" . mysql_real_escape_string(serialize($info), $this->db) . "' WHERE id = " . $cmdInfo['id'];
		mysql_query($sql, $this->db);
	}

	public function setQueueCmd($cmdInfo, $cmd)
	{
		$this->log('устанавливаем команду ' . $cmd . ' для id очереди=' . $cmdInfo['id']);
		$this->db = $this->connectDb("mycloud", $this->db);
		$sql = 'UPDATE dm_income_queue SET cmd_id = ' . $cmd . ' WHERE id = ' . $cmdInfo['id'];
		mysql_query($sql, $this->db);
	}

	/**
	 * проверка завершения работы командного файла текущей операции
	 * признак завершения операции - наличие файла с расширением ".complete"
	 *
	 * @return boolean
	 */
	public function operationIsComplete()
	{
		$completeFile = $this->batName . ".complete";
		if (file_exists($completeFile))
		{
			unlink($completeFile);
			return true;
		}
		return false;
	}

	/**
	 * проверка наличия ошибок при выполнении командного файла текущей операции
	 * признак ошибок - наличие файла с расширением ".errors" ненулевого размера
	 *
	 * @return integer
	 */
	public function operationIsFailed()
	{
		$errorsFile = $this->batName . ".errors";
		if (file_exists($errorsFile))
		{
			$sz = filesize($errorsFile);
			if (empty($sz))
				unlink($errorsFile);
			return $sz;
		}
		return 0;
	}


    protected $transport;
	/**
	 * выполнение очереди заданий пространства
	 * @var partnerTransport
	 */
	public function queue()
	{
		$this->threadCount = _THREADS_CNT_;
		$this->db = $this->connectDb("mycloud", $this->db);
		$sql = 'SELECT * FROM dm_income_queue WHERE partner_id = ' . _PARTNER_ID_ . ' AND cmd_id < ' . _CMD_DONE_ . ' AND state < ' . _STATE_ERR_ . ' AND station_id IN (0, ' . _STATION_ . ') ORDER BY priority DESC, cmd_id DESC, state DESC';
		$q = mysql_query($sql, $this->db);
		$cnt = mysql_num_rows($q);
		while ($cmdInfo = mysql_fetch_assoc($q))
		{
			if (empty($cmdInfo['info']))
			{
/*
				$info = array(
					'files' => array('/m/megamozg_2010.avi', '', ...),
					'ovids' => array, МАССИВ ИДЕНТИФИКАТОРОВ ОРИГИНАЛЬНЫХ ВАРИАНТОВ
					'md5s' => array('', '' ...),
					'tags'	=> array(
						"title"				=> 'Название фильма',
						"title_original"	=> 'Оригинальное название фильма',
						"genres"			=> 'Жанр1, Жанр2, Жанр3',
						"description"		=> 'Длинное "очень-очень" описание фильмаДлинное "очень-очень" описание фильмаДлинное "очень-очень" описание фильмаДлинное "очень-очень" описание фильмаДлинное "очень-очень" описание фильма',
						"year"				=> '2012',
						"poster"			=> 'apple.png',
					),
				);
				$cmdInfo['info'] = serialize($info);
*/
			}
			$this->run($cmdInfo);

			if (!empty($this->transport->errorNo))
			{
				//ОШИБКА ВЫПОЛНЕНИЯ. ВЫХОДИМ
				$cnt = 0;
				$this->treadCount = 0;
				break;
			}

			if ($this->threadCount <= 0)
			{
				$this->log('сработало ограничение по количеству потоков max=' . _THREADS_CNT_);
				break;
			}
			if (!empty($this->cmdContent))
				break;//ВЫПОЛНЯЕМ ПО ОДНОМУ КОМАНДНОМУ ФАЙЛУ НА КАЖДЫЙ ЗАПУСК КОНВЕРТЕРА
		}

		mysql_free_result($q);

		if (empty($cnt) && !empty($this->threadCount))
		{
//return; //ВРЕМЕННО НЕ ГЕНЕРИРУЕМ ОЧЕРЕДЬ. ОБРАБАТЫВАЕМ ТОЛЬКО ЗАЯВКИ ОТ ПОЛЬЗОВАТЕЛЕЙ
			$this->log(_PARTNER_ . ' очередь партнера пуста. Запрос новой очереди');
			$checkConn = $this->transport->checkConnections();
			if ($checkConn)
			{
				$this->log($checkConn);
				return;
			}
			$queue = $this->transport->createQueue();
			if ($queue !== false)
			{
				$this->log('Получено в обработку ' . count($queue) . ' записей партнера ' . _PARTNER_);
				if (!empty($queue))
				{
					foreach ($queue as $q)
					{
						$info = array(
							'just_online'	=> $q['just_online'],
							'files'			=> $q['files'],
							'tags'			=> $q['tags'],
							'md5s'			=> $q['md5s'],
							'ovids'			=> $q['ovids'],
							'group_id'		=> (empty($q['group_id'])) ? 0 : $q['group_id'],
						);

						$qInfo = array(
							'product_id'	=> 0,//ДОБАВЛЯЕМ ФАЙЛЫ ПРОСТО НА КОНВЕРТИРОВАНИЕ (В ВИТРИНЫ ДОБАВЛЕНИЯ НЕ БУДЕТ)
							'original_id'	=> $q['original_id'],//оригинальный ID на объект партнера (например фильм)
							'task_id'		=> 0, //идентификатор задания в очереди заданий данного компрессора
							'cmd_id'		=> _CMD_COPY_, //КОПИРОВАНИЕ - ПЕРВОЕ ДЕЙСТВИЕ НАД ОБЪЕКТОМ ОЧЕРЕДИ
							'priority'		=> 0,
							'state'			=> _STATE_WAIT_,
							'station_id'	=> _STATION_,
							'partner_id'	=> _PARTNER_ID_,
							'user_id'				=> 0,
							'original_variant_id'	=> 0,
							'info'					=> serialize($info),
							'date_start'	=> date('Y-m-d H:i:s')
						);
						$sql = 'INSERT INTO dm_income_queue
						(id, product_id, original_id, task_id, cmd_id, priority, state, station_id, partner_id, info, user_id, original_variant_id)
						VALUES
						(id, ' . $qInfo['product_id'] . ', ' . $qInfo['original_id'] . ', ' . $qInfo['task_id'] . ', ' .
						$qInfo['cmd_id'] . ', ' . $qInfo['priority'] . ', ' . $qInfo['state'] . ', ' . $qInfo['station_id'] . ', ' .
						$qInfo['partner_id'] . ', \'' . mysql_real_escape_string($qInfo['info'], $this->db) . '\', ' .
						$qInfo['user_id'] . ', ' . $qInfo['original_variant_id'] . ')
						';
						mysql_query($sql, $this->db);
					}
				}
			}
			else
			{
				$this->log('транспорт партнера ' . _PARTNER_ . ' вернул ошибку. ' .$this->transport->errorMsg);
			}
		}
	}

	public function prepareMp4Tags($s)
	{
		$s = html_entity_decode($s, ENT_COMPAT, _SOURCE_CHARSET_);
		$s = strip_tags($s);
		$s = preg_replace('/:/u', '.', $s);
		$s = preg_replace('/"/u', "'", $s);
		$s = preg_replace("/[\n]/u", "", $s);
		$s = mb_substr($s, 0, 250, _SOURCE_CHARSET_);
		return $s;
	}

	public function getPosterKeys($r)
	{
		$poster = '';
		if (!empty($r['poster']))
		{
			$poster = $r['poster'];
			//$poster = '--artwork ' . _POSTER_PATH_ . $p;
			$poster = '-z --add ' . _POSTER_PATH_ . _SL_ . $poster;
			//$txtInfo[] = "Постер\n" . _POSTER_PATH_ . _SL_ . $poster;
		}

		return $poster;
	}

	/**
	 * получить тэги для фильма в формате MP4BOX
	 *
	 * @param mixed $r - массив тэгов
	 * @param mixed $dir - директория где лежит файл
	 * @param mixed $fullName - название файла
	 * @return string
	 */
	public function getTagsKeys($r, $dir = '', $fullName = '')
	{
		$isMovie = true;
		$season = '';
		$episode = '';
		$episodeId = '';
		$matches = array();
		$tagsFileName = 'tags.txt';
//$fullName.='e0' . $e . '_episode_name.avi';// . '_rus_eng_episode_name.avi';
		preg_match('/e([0-9]{2,})/i', $fullName, $matches);//ИЩЕМ НУМЕРАЦИЮ ЭПИЗОДА
		if (!empty($matches[1]))
		{
			$isMovie = false;
			$episode = intval($matches[1]);
			$episodeId = $matches[1];
			$season = 1;
			$tagsFileName = 'tags_e' . $matches[1] . '.txt';
			$subStr = 'e' . $matches[1];
		}

		$matches = array();
		preg_match('/s([0-9]{2,})e([0-9]{2,})/i', $fullName, $matches);//ИЩЕМ НУМЕРАЦИЮ СЕЗОНА И ЭПИЗОДА
		if (!empty($matches[2]))
		{
			$isMovie = false;
			$season = intval($matches[1]);
			$episode = intval($matches[2]);
			$episodeId = $matches[2];
			$tagsFileName = 'tags_s' . $matches[1] . 'e' . $matches[2] . '.txt';
			$subStr = 's' . $matches[1] . 'e' . $matches[2];
		}

		$txtInfo = array();//ДЛЯ ВЫВОДА ТЭГОВ В ФАЙЛ
		if (empty($r['title'])) $r['title'] = '';
		if (empty($r['title_original'])) $r['title_original'] = '';
		if (!$isMovie)
		{
			$r['title'] = trim(mb_eregi_replace("сезон" .'[\s]+[\d]+', '', $r['title']));
			$r['title_original'] = trim(mb_eregi_replace("season" .'[\s]+[\d]+', '', $r['title_original']));
		}
		//$title = $this->prepareMp4Tags(implode("/", array($r['title'], $r['title_en'])));
		$title = $this->prepareMp4Tags($r['title_original']);
		$show = '';
		if (!$isMovie)
		{
			$show = $title;
			$txtInfo[] = "Название сериала\n" . $show;
			$show = '-S "' . $show . '"';//MP4TAGS

			//$title = basename($this->prepareMp4Tags($fullName));//НО ПО ИДЕЕ СЮДА НУЖНО НАЗВАНИЕ СЕРИИ! НО ПОКА ПИШЕМ ИМЯ ФАЙЛА

			$episodeNameInfo = pathinfo($fullName);
			$episodeName = $episodeNameInfo["basename"];
			$pos = strpos($episodeName, $subStr) + strlen($subStr);
			if ($pos)
			{
				$episodeName = substr($episodeName, $pos);
				$episodeName = str_replace("rus.", "", $episodeName);
				$episodeName = str_replace("rus_", "", $episodeName);
				$episodeName = str_replace("eng.", "", $episodeName);
				$episodeName = str_replace("eng_", "", $episodeName);
			}

			$episodeName = str_replace("_", " ", $episodeName);
			$episodeName = str_replace(".", " ", $episodeName);
			$episodeName = preg_replace("/[\s]{2,}/u", "", $episodeName);
			if ($episodeName == ' ') $episodeName = '';

			$episodeName = substr($episodeName, 0, 0 - strlen($episodeNameInfo["extension"]));//ОТРЕЗАЛИ РАСШИРЕНИЕ
			//$episodeName = implode(" / ", array("Серия " . sprintf("%02d", $episode), trim($episodeName)));
			$episodeName = implode(" ", array($subStr, trim($episodeName)));
			//$episodeName = iconv('windows-1251', 'utf-8', $episodeName);
			$title = $episodeName;
			$txtInfo[] = "Название серии\n" . $episodeName;
			$episodeId = '-o "' . $episodeName . '"';

			$txtInfo[] = "Номер сезона\n" . $season;
			$season = "-n " . $season;

			$txtInfo[] = "Номер серии\n" . $episode;
			$episode = "-M " . $episode;
		}

		$txtInfo[] = "Название\n" . $title;
		$title = '-s "' . $title . '"';//MP4BOX
		//$title = '--title "' . $this->prepareMp4Tags(implode("/", $title)) . '" lang=rus ' . _CONSOLE_CHARSET_;//ATOMICPARSLET

		$genre = '';
		if (!empty($r['genres']))
		{
			$genre = '-g "' . $this->prepareMp4Tags($r['genres']) . '"';//MP4BOX
			$txtInfo[] = "Жанр\n" . $this->prepareMp4Tags($r['genres']);
		}

		//$genre = '--genre "' . $this->prepareMp4Tags(implode(', ', $genres)) . '" lang=rus ' . _CONSOLE_CHARSET_;//ATOMICPARSLET

		if (empty($r["description"]))
		{
			$r["description"] = "";
		}
		$description = '-m "' . $this->prepareMp4Tags($r["description"]) . '"';//MP4BOX
		//$description = '--comment "' . $this->prepareMp4Tags($r["description"]) . '" lang=rus ' . _CONSOLE_CHARSET_;//ATOMICPARSLET
		$txtInfo[] = "Описание\n" . $this->prepareMp4Tags($r["description"]);

		$longdesc = '-l "' . $this->prepareMp4Tags($r["description"]) . '"';//MP4BOX
		//$longdesc = '--description "' . $this->prepareMp4Tags($r["description"]) . '" lang=rus ' . _CONSOLE_CHARSET_;//ATOMICPARSLET
		$txtInfo[] = "Полное описание\n" . $this->prepareMp4Tags($r["description"]);

		$comment = '-c "' . $this->prepareMp4Tags($r["description"]) . '"';//MP4BOX
		//$longdesc = '--description "' . $this->prepareMp4Tags($r["description"]) . '" lang=rus ' . _CONSOLE_CHARSET_;//ATOMICPARSLET
		$txtInfo[] = "Комментарий\n" . $this->prepareMp4Tags($r["description"]);

		$year = '';
		if (!empty($r["year"]))
		{
			$year = '-y "' . $r["year"] . '"';//MP4BOX
			//$year = '--year ' . $r["year"];//ATOMICPARSLET
			$txtInfo[] = "Год\n" . $r["year"];
		}
		if ($isMovie)
			$type = 'movie'; //movie, tvshow etc
		else
			$type = 'tvshow'; //movie, tvshow etc
		$movie = '-i "' . $type . '"';//MP4BOX
		//$movie = '--stik "Movie"';//ATOMICPARSLET
		$txtInfo[] = "Медиа тип\n" . $type;

//ТЭГИ ПИШЕМ В КОДИРОВКЕ КОНСОЛИ
		$tags2file = iconv('utf-8', _CONSOLE_CHARSET_.'//IGNORE', implode("\n\n\n", $txtInfo));
		file_put_contents($dir . _SL_ . $tagsFileName, $tags2file);

		$tags = iconv('utf-8', _CONSOLE_CHARSET_.'//IGNORE', implode(' ', array($title, $genre, $season, $episode, $episodeId, $show, $description, $movie, $longdesc, $year, $comment)));
		$this->log('сгенерированы тэги для фильма');
		return $tags;
	}

	/**
	 * создать дерево каталогов или проверить существуют ли все подкаталоги дерева
	 *
	 * @param string $path
	 * @return boolean
	 */
	function createTree($path)
	{
		$result = file_exists($path);
		if ($result) return $result;

		$root = explode(_ROOT_PATH_, $path);
		if (count($root) > 1)
		{
			$dirs = explode('/', $root[1]);
			$curDir = _ROOT_PATH_;
			foreach ($dirs as $d)
			{
				if (empty($d)) continue;
				$curDir .= '/' . $d;
				$result = (file_exists($curDir) && (is_writable($curDir)));
				if (!$result)
				{
					$result = mkdir($curDir, 0755);
				}
				if (!$result) break;
			}
		}
		return $result;
	}

	protected $dbs;

	/**
	 * название командного файла текущей сессии
	 *
	 * @var string
	 */
	protected $batName;

	/**
	 * список команд командного файла
	 *
	 * @var string
	 */
	protected $cmdContent;

	/**
	 * коннект к БД (handle)
	 *
	 * @var integer
	 */
	protected $db = 0;

	/**
	 * текущая подключенная БД
	 *
	 * @var string
	 */
	protected $currentDb = '';

	/**
	 * счетчик исполняемых потоков на данном компрессоре
	 *
	 * @var integer
	 */
	protected $threadCount = 0;

	protected function connectDb($cfgName, $db = 0)
	{
		if (!empty($db)) return $db;

		$this->log('Подключение к БД...');
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorNo = _ERR_DB_;
			$msg = 'Ошибка соединения с БД (' . $cfgName . ') ' . mysql_errno();
			$this->errorMsg = $msg;
			$msg = iconv(_SOURCE_CHARSET_, _CONSOLE_CHARSET_, $msg);
			$this->log($msg);
			$this->releaseLog();
			die($msg);
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);
		return $db;
	}

	protected function closeDb($db = 0)
	{
		if ($db)
		{
			mysql_close($db);
		}
	}

	/**
	 * подготовка лог-файла. Необходимо запускать в начале работы
	 * Проверяет наличие лог-файла предыдущего запуска и блокирует исполнение
	 * при наличии current.log
	 *
	 */
	protected function initLog()
	{
		$this->createTree(_LOG_PATH_);
		$this->createTree(_COPY_PATH_);
		$this->createTree(_CONV_PATH_);
		$this->createTree(_READY_PATH_);
		$this->createTree(_CMD_PATH_);
		$this->createTree(_TMP_PATH_);
		$this->createTree(_POSTER_PATH_);

		if (empty($this->logFileName))
		{
			$this->logFileName = 'current.' . _PARTNER_ . '.log';
			$this->crashFileName = 'current.' . _PARTNER_ . '.crash';
		}

		$current = _LOG_PATH_ . _SL_ . $this->logFileName;
		if (file_exists($current))
		{
			//ЗНАЧИТ ПРОДОЛЖАЕТСЯ ВЫПОЛНЕНИЕ ИЛИ БЫЛО ФАТАЛЬНОЕ ПРЕКРАЩЕНИЕ РАБОТЫ
			//ПРОВЕРЯЕМ КРАШКОД
			if (file_exists(_LOG_PATH_ . _SL_ . $this->crashFileName))
			{
				$crashCode = file_get_contents(_LOG_PATH_ . _SL_ . $this->crashFileName);
			}
			if (!empty($crashCode))
			{
				$restartName = 'current.' . _PARTNER_ . '.restart.' . date('Y-m-d_H-i-s') . '.log';
				//АНАЛИЗИРУЕМ КОД ИЛИ ЖДЕМ ОТСЕЧКУ ПО ВРЕМЕНИ
				switch ($crashCode)
				{
					case _ERR_NO_FILESERVER_:
						//ПРОВЕРЯЕМ СОЕДИНЕНИЕ С ФАЙЛОВЫМИ СЕРВЕРАМИ
						if (!$this->checkFileConnections())
						{
							//ОШИБОК НЕТ
							rename($current, _LOG_PATH_ . _SL_ . $restartName);
							unlink(_LOG_PATH_ . _SL_ . $this->crashFileName);
						}
					break;

					case _ERR_COPYFILE_:
						//ПЕРЕХОДИМ К СЛЕДУЮЩЕЙ ОПЕРАЦИИ
						rename($current, _LOG_PATH_ . _SL_ . $restartName);
						unlink(_LOG_PATH_ . _SL_ . $this->crashFileName);
					break;

					case _ERR_DB_:
						//ПРОВЕРЯЕМ СОЕДИНЕНИЕ С БД
						if (!$this->checkDBConnections())
						{
							//ОШИБОК НЕТ
							rename($current, _LOG_PATH_ . _SL_ . $restartName);
							unlink(_LOG_PATH_ . _SL_ . $this->crashFileName);
						}
					break;

					case _ERR_NO_WEBSERVER_:
						//СКАЧКА ТЕСТОВОГО ФАЙЛА
						/**
						 * !!! К РЕАЛИЗАЦИИ !!!
						 * //тестовый файл test.mp4 должен лежать в корне директории content конвертера
						 * $cmd = "wget -O test.mp4.piece http://127.0.0.1:81/test.mp4?start=10"
						 *
						 * если тестовое скачивание прошло успешно, рестартуем конвертер
						 *
						 */
						rename($current, _LOG_PATH_ . _SL_ . $restartName);
						unlink(_LOG_PATH_ . _SL_ . $this->crashFileName);
					break;

					default:
						//ЖДЕМ НЕКОТОРЕ ВРЕМЯ, ПОТОМ ПЕРЕИМЕНОВЫВАЕМ ЗАВИСШИЙ ЛОГ
						$curTime = time();
						clearstatcache();
						$fileTime = filemtime($current);
						if ($fileTime && ($curTime - $fileTime > 3600 * 3))//ТРИ ЧАСА
						{
							rename($current, _LOG_PATH_ . _SL_ . $restartName);
							unlink(_LOG_PATH_ . _SL_ . $this->crashFileName);
							die(iconv(_SOURCE_CHARSET_, _CONSOLE_CHARSET_, 'Перезапускаем после простоя. подробнее см. лог-файл ' . $restartName));
							return;
						}
				}
			}
			else
			{
				die(iconv(_SOURCE_CHARSET_, _CONSOLE_CHARSET_, 'Скрипт уже запущен или принудительно завершен. подробнее см. лог-файл ' . $current));
				return;
			}
		}

		$f = fopen($current, 'w+'); //создать пустой лог для данной сессии
		if (!$f)
		{
			die(iconv(_SOURCE_CHARSET_, _CONSOLE_CHARSET_, 'Невозможно создать лог ' . $current));
		}
		fclose($f);

		$this->log('--=== НОВЫЙ ЗАПУСК СКРИПТА ===--');
	}

	/**
	 * сохранить лог-файл текущей сессии
	 *
	 */
	public function releaseLog()
	{
		$current = _LOG_PATH_ . _SL_ . $this->logFileName;
		if (!empty($this->errorNo))
		{
			$crash = _LOG_PATH_ . _SL_ . $this->crashFileName;
			file_put_contents($crash, $this->errorNo);
			return;
		}

		if (file_exists($current))
		{
			rename($current, _LOG_PATH_ . _SL_ . date('Y-m-d_H-i-s') . '.log');
		}
	}

	/**
	 * подготовка bat-файла. Необходимо запускать в начале работы
	 *
	 */
	protected function initBat($cmdInfo)
	{
		$this->batName = _CMD_PATH_ . _SL_ . _PARTNER_ . '.' . $cmdInfo['id'] . '.sh';
		if (file_exists($this->batName))
		{
			if ($cmdInfo['state'] && filesize($this->batName))
			{
				//НЕ ПЕРЕСОЗДАЕМ КОМАНДНЫЙ ФАЙЛ, ЕСЛИ ОПЕРАЦИЯ СТАРТОВАЛА
				$this->batName = '';
				return false;
			}
		}
		if (empty($cmdInfo['state']))
		{
			$completeFile = $this->batName . ".complete";
			if (file_exists($completeFile))
			{
				//ПРИ ПЕРЕЗАПУСКE ОПЕРАЦИЙ УДАЛЯЕМ СТАРЫЙ
				unlink($completeFile);
			}
		}
		$this->log('Инициализируем командный файл ' . $this->batName);
		$f = fopen($this->batName, 'w+'); //создать пустой лог для данной сессии
		if (!$f)
		{
			$this->batName = '';
			return false;
		}
		fclose($f);
		$this->cmdContent = '';
		return true;
	}

	/**
	 * добавить строку в командный файл
	 *
	 * @param integer $product_id	- идентификатор очереди
	 * @param string $cmd		- команда
	 * @param boolean $complete - генерировать ли файл-признак после выполнения команды (для многострочных исполняемых файлов)
	 */
	public function cmd($id = 0, $cmd = '', $complete = true)
	{

		if (empty($id)) return;
		if (!empty($cmd))
		{
			$this->log('добавляем запись в командный файл для ' . _PARTNER_ . ' id очереди=' . $id . ' (' . $cmd . ')');
			//$this->cmdContent .= "echo {$film_id}-inprocess > " . _CMD_PATH_ . _SL_ . "{$film_id}.out"; //ЭМУЛИРУЕМ ВЫВОД
			//$this->cmdContent .= "\n";
			$this->cmdContent .= $cmd;// . " > " . _CMD_PATH_ . _SL_ . "{$film_id}.out"; //ВЫВОД КОМАНДЫ НАПРАВЛЯЕМ В ФАЙЛ
			$this->cmdContent .= "\nif [ $? -eq 0 ]\nthen\necho success\nelse\necho failure >&2\nfi\n";
		}
		if ($complete)
		{
			//$this->cmdContent = "#!/bin/bash\n" . $this->cmdContent;
			$this->cmdContent .= 'echo ok > ' . $this->batName . '.complete'; //ПРИЗНАК ОКОНЧАНИЯ ВЫПОЛНЕНИЯ КОМАНДНОГО ФАЙЛА
			$this->cmdContent .= "\n" . 'cp --remove-destination ' . $this->batName . ' ' . $this->batName . '.backup'; //БЭКАП КОМАНДНОГО ФАЙЛА
			$this->cmdContent .= "\n" . 'rm -f ' . $this->batName; //ПРИЗНАК ОКОНЧАНИЯ ВЫПОЛНЕНИЯ КОМАНДНОГО ФАЙЛА
		}
	}

	public function exec()
	{
		$this->log('исполняем командный файл ' . $this->batName);
		if (empty($this->cmdContent))
		{
			$this->log("Нечего исполнять. Список команд пуст.");
			if (!empty($this->batName) && file_exists($this->batName))
				unlink($this->batName);
			$this->releaseLog();
			return;
		}
		$this->releaseLog();
		$f = fopen($this->batName, 'a+'); //дописываем в конец
		fwrite($f, $this->cmdContent);
		fclose($f);
//return ;
		exec("sh " . $this->batName . " 2> " . $this->batName . ".errors");
		//system("sh " . $this->batName . " 2> " . $this->batName . ".errors");
	}

	public function log($str = '')
	{
		$current = _LOG_PATH_ . _SL_ . $this->logFileName;
		$f = fopen($current, 'a+'); //дописываем в конец
		fwrite($f, date('Y-m-d H:i:s') . "\t" . iconv(_SOURCE_CHARSET_, _CONSOLE_CHARSET_, $str) ."\n");
		fclose($f);
	}

	public function __construct($dbs)
	{
		$this->errorMsg = '';
		$this->errorNo = _ERR_OK_;
		if (!setlocale(LC_ALL, 'ru_RU'))
			setlocale(LC_ALL, 'rus');
		$this->dbs = $dbs;
		$this->initLog();
		$this->checkConnections();
		if (empty($this->errorNo))
		{
			$this->transport = new partnerTransport($dbs);
			$this->queue();
			$this->exec();
		}
		else
		{
			$this->releaseLog();
		}
	}

	public function __destruct()
	{
		$this->closeDb($this->db);
	}

	/**
	 * метод проверки связи с файловыми серверами
	 *
	 */
	public function checkFileConnections()
	{
//ПРОВЕРЯЕМ КОННЕКТЫ С ФАЙЛОВЫМИ СЕРВЕРАМИ
		$this->errorNo = _ERR_OK_;
		$this->errorMsg = '';
		$paths = array(
			_LOG_PATH_,
			_COPY_PATH_,
			_CONV_PATH_,
			_READY_PATH_,
			_CMD_PATH_,
			_TMP_PATH_,
			_POSTER_PATH_
		);
		foreach ($paths as $p)
		{
			if (!file_exists($p))
			{
				$this->errorNo = _ERR_NO_FILESERVER_;
				$this->errorMsg = 'невозможно обратиться к файловому серверу (путь ' . $p . ')';
				return $this->errorMsg;
			}
		}

		return $this->errorMsg;
	}

	/**
	 * метод проверки связи с серверами БД
	 *
	 */
	public function checkDBConnections()
	{
		$this->errorNo = _ERR_OK_;
		$this->errorMsg = '';
//ПРОВЕРЯЕМ КОННЕКТЫ С БД
		foreach ($this->dbs as $d)
		{
			$db = mysql_connect($d['host'], $d['user'], $d['pwd'], true);
			if (!$db)
			{
				$this->errorNo = _ERR_DB_;
				$this->errorMsg = 'ошибка соединения с БД ' . $d['host'] . '@' . $d['user'];
				return $this->errorMsg;
			}
			mysql_select_db($d['name'], $db);
			mysql_close($db);
		}
		return $this->errorMsg;
	}

	/**
	 * метод проверки связи с серверами БД и файловыми серверами
	 *
	 */
	public function checkConnections()
	{
		$err = $this->checkDBConnections();
		if ($err) return $err;

		$err = $this->checkFileConnections();
		return $err;
	}
}

ini_set('safe_mode_exec_dir', _CMD_PATH_);
$converter = new cConverter($dbs);
