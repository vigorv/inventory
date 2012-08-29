<?php

interface iConverterTransport
{
	/**
	 * генерирует команды копирования файлов продукта
	 *
	 * @param mixed $files - массив относительных путей к файлам продукта
	 * @return mixed - возвращает список команд копирования файлов из _SRC_PATH_ В _COPY_PATH_
	 */
	public function copyFiles($files);

	/**
	 * генерирует команду копирования постера продукта
	 *
	 * @param mixed $posters - массив относительных путей к постерам продукта (используется индекс 'poster')
	 * @return mixed - возвращает список команд скачивания/копирования постеров продукта В _POSTER_PATH_
	 */
	public function copyPosters($posters);

	/**
	 * сгенерировать команду копирования сконвертированного файла
	 * в хранилище партнера
	 *
	 * может вернуть пустую команду, если партнер решает не копировать файл по своему усмотрению
	 *
	 * @param string $oldName = оригинальное имя файла
	 * @param string $newName - готовый файл (полный путь)
	 * @param string $subDir - субдиректория (для разного качества конвертирования)
	 * @return string
	 */
	public function copyOutCmd($oldName, $newName, $subDir);

	/**
	 * обновление информации о старом файле и внесение информации о новом (сконвертированном)
	 *
	 * @param integer $originalId
	 * @param string $oldName
	 * @param string $newName
	 * @param string $preset
	 * @param mixed $fInfo - инфо  нового файла (размер в байтах, md5-хэш итд
	 */
	public function saveBack($originalId, $oldName, $newName, $preset, $fInfo);

	/**
	 * вызывает метод createQueue для указанного продукта (по $originalId)
	 *
	 * @param integer $originalId
	 * @param integer $originalVariantId
	 * @return mixed
	 */
	public function getObjectToQueue($originalId, $originalVariantId = 0);

	/**
	 * получить массив данных о необработанных продуктах
	 *
	 * формат возвращаемой структуры
	 * 					$info['original_id'];
	 * 					$info['group_id'] - идентификатор группировки
	 * 						для возможности добавления сложносоставных продуктов,
	 * 						например, поочередное добавление серий в сезон сериала
	 * 					$info['just_online'];
						$info['md5s'] = array(хэши md5, соответствующих файлов)
						$info['files'] = array(
							относительный путь к I-му файлу объекта в примонтированной директории
							относительный путь к II-му файлу объекта в примонтированной директории
							...
						);
						$info['tags'] = array(
							"title"				=> title			- название объекта
							"title_original"	=> title_original	- оригинальное название объекта
							"genres"			=> genres			- жанры через запятую
							"countries"			=> countries		- страны через запятую
							"description"		=> description		- описание объекта
							"year"				=> year				- год
							"poster"			=> poster			- относительный путь к файлу постера
						);
	 * @return mixed
	 */
	public function createQueue($condition = '');

	/**
	 * метод проверки связи с серверами БД и файловыми серверами
	 *
	 * @return boolean
	 */
	public function checkConnections();

	/**
	 * вызов действия сброса кэша на ресурсе партнера
	 *
	 * $info['original_id'] - идентификатор продукта в системе партнера
	 *
	 * @param mixed $info
	 */
	public function clearCache($info);

	/**
	 * вызов действия обновления инфо сконвертированного объекта в БД media1
	 *
	 * @param integer $originalId
	 * @param string $oldName
	 * @param string $newName
	 * @param string $preset
	 * @param mixed $fInfo - инфо  нового файла (размер в байтах, md5-хэш итд
	 */
	public function updateMedia1($originalId, $oldName, $newName, $preset, $fInfo);
}
