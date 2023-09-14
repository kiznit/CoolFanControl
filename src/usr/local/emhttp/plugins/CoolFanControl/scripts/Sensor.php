<?php

class Sensor {
    function __construct($_path) {
        $this->$path = $_path;
    }

    function Read() {
        return intval(exec("cat ".$this->$path));
    }

    private $path;
}


class FanSensor extends Sensor {
}


class TempSensor extends Sensor {
    function Read() {
        return parent::Read() / 1000;
    }
}


$fan = new FanSensor("/sys/class/hwmon/hwmon9/fan1_input");
$temp = new TempSensor("/sys/class/hwmon/hwmon9/temp3_input");

echo "hello, temp is: ", $temp->Read(), ", fan speed is: ", $fan->Read(), "\n";

?>
