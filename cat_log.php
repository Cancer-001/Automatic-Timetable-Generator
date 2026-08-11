<?php
$lines = file('C:\xampp\apache\logs\error.log');
echo implode("", array_slice($lines, -30));
