<?php

Stack\Config::loadDefaults();

if (defined('UPLOADS_FTP_HOST') && UPLOADS_FTP_HOST != "") {
    new Stack\FTPStorage(UPLOADS_FTP_HOST);
}
