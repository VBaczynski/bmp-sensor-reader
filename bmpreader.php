<?php
/* ------------------------------------------------------------------------------------------------
Copyright © 2016, Viacheslav Baczynski, @V_Baczynski
License: MIT License

BMP sensor reader, v1.0.

Reading data from and writing to BMP085 sensor via I2C.

Info: The BMP085 consists of a piezo-resistive sensor, an analog to digital converter
and a control unit with E2PROM and a serial I2C interface. The BMP085 delivers the
uncompensated value of pressure and temperature. The E2PROM has stored 176 bit of
individual calibration data. This is used to compensate offset, temperature dependence
and other parameters of the sensor.
• UP = pressure data (16 to 19 bit)
• UT = temperature data (16 bit)

------------------------------------------------------------------------------------------------ */

// IIC library
include 'phpi2c.php';

// connection info (used in IIC library finctions)
$block = "i2c-gpio0";	// block name on the system
$i2c_address = "0x77";	// i2c slave address for bmp085
// 

// i2c device operation modes
# low power			-> 0
# standard			-> 1
# high res			-> 2
# ultra high res	-> 3
$oss = 1; // oversampling setting

$sleep_time = array(
	0 => 4600, // 4.5 ms according to documentation, but let's put a little bit more
	1 => 7600, // 7.5 ms
	2 => 13600, // 13.5 ms
	3 => 25600 // 25.5 ms
);

// chip calibration coefficients (static for sensor and can be saved in a file)
$ac1	= read_short(  0xAA );
$ac2	= read_short(  0xAC );
$ac3	= read_short(  0xAE );
$ac4	= read_ushort( 0xB0 );
$ac5	= read_ushort( 0xB2 );
$ac6	= read_ushort( 0xB4 );
$b1		= read_short(  0xB6 );
$b2		= read_short(  0xB8 );
$mb		= read_short(  0xBA );
$mc		= read_short(  0xBC );
$md		= read_short(  0xBE );

// sensor readings
$ut	= 0; // uncompensated temperature
$t	= 0; // true temperature
$up	= 0; // uncompensated pressure
$p	= 0; // true pressure
$a	= 0; // altitude (calculated using true pressure)

// reading uncompensated temperature
write_register( 0xF4, 0x2E );
usleep( 4600 ); // Should be not less then 4500
$ut = read_short( 0xF6 );

// reading uncompensated pressure
write_register( 0xF4, 0x34 + ($oss << 6) );
usleep( $sleep_time[$oss] );
$up = read_ulong( 0xF6 );
$up = $up >> (8 - $oss);

// calculating true temperature
$x1 = (($ut - $ac6) * $ac5) >> 15;
$x2 = ($mc << 11) / ($x1 + $md);
$b5 = $x1 + $x2;
$t = (($b5 + 8) >> 4 ) / 10;

// calculating true pressure
$b6 = $b5 - 4000;
$x1 = ($b2 * (($b6 ^ 2) >> 12)) >> 11;
$x2 = ($ac2 * $b6) >> 11;
$x3 = $x1 + $x2;
$b3 = ((($ac1 * 4 + $x3) << $oss) + 2) / 4;
$x1 = ($ac3 * $b6) >> 13;
$x2 = ($b1 * ($b6 ^ 2) >> 12) >> 16;
$x3 = (($x1 + $x2) + 2) >> 2;
$b4 = ($ac4 * ($x3 + 32768)) >> 15;
$b7 = ($up - $b3) * (50000 >> $oss);
if ($b7 < 0x80000000) {
	$p = ($b7 * 2) / $b4;
} else {
	$p = ($b7 / $b4 ) * 2;
}
$x1 = ($p >> 8) * ($p >> 8);
$x1 = ($x1 * 3038) >> 16;
$x2 = (-7357 * $p) >> 16;
$p = $p + (($x1 + $x2 + 3791) >> 4);

// calculating absolute altitude
$a = 44330 * ( 1 - pow( ( $p / 101625 ), 0.1903 ) );

// calculating pressure at sea level
$p0 = $p / pow( (1 - $a / 44330), 5.255 );

// converting Pressure from hPa to mm Hg
$p = intval( $p / 1.3332239);
$p = $p / 100;
$p0 = intval( $p0 / 1.3332239);
$p0 = $p0 / 100;

echo "Temperature: " . $t . " C.\nPressure: " . $p . " mm Hg.\nAbsolute altitude: " . $a . "m.\nCalculated pressure at sea level: " . $p0 ."\n";
?>