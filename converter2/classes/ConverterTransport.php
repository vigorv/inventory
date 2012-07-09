<?php
/**
 * Created by JetBrains PhpStorm.
 * User: snow
 * Date: 06.07.12
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */
interface ConverterTransport
{
    function getObjectToQueue($originalId, $originalVariantId = 0);
    function checkConnections();
    function copyFiles($files);
    function copyPosters($posters);
    function copyOutCmd($oldName, $newName, $subDir);
    function saveBack($originalId, $oldName, $newName, $preset, $fInfo);
    function dropOriginal($originalId);
    function clearCache($info);
    function createQueue($condition = '');
    function getErrorMsg();
}
