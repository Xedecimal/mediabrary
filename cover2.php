<?php

header('Content-Type: image/jpeg');
die(file_get_contents($_GET['path']));