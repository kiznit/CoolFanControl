<?php

function clamp($value, $min, $max) {
    return max($min, min($max, $value));
}

// Fan power range is 0-255
class Fan {
    function __construct($_path) {
        $this->$path = $_path;
    }

    function GetPower() {
        return intval(exec("cat ".$this->$path));
    }

    function SetPower($value) {
        $value = clamp(round($value), 0, 255);
        exec("echo ${value} > ".$this->$path);
    }

    private $path;
}


$fan = new Fan("/sys/class/hwmon/hwmon9/pwm2");

echo "hello, fan setting is: ", $fan->GetPower(), "\n";
$fan->SetPower(50);
echo "hello, fan setting is: ", $fan->GetPower(), "\n";
$fan->SetPower(200);
echo "hello, fan setting is: ", $fan->GetPower(), "\n";
$fan->SetPower(10);

?>
