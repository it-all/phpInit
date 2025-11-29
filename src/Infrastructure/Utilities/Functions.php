<?php
declare(strict_types=1);
namespace Infrastructure\Utilities;

/**
 *  A place to store php helper functions, not app-related functions
 */
class Functions
{
    /** note, array fields with no checked options will be set null */
    public static function getRequestInput(array $inputVars, array $fieldNames): array
    {
        $requestInput = [];
        foreach ($fieldNames as $fieldName) {
            if (isset($inputVars[$fieldName])) {
                $value = $inputVars[$fieldName];
                if (is_string($value)) {
                    $requestInput[$fieldName] = trim($value);
                } else {
                    $requestInput[$fieldName] = $value;
                }
            } else {
                $requestInput[$fieldName] = null;
            }
        }
        return $requestInput;
    }

    public static function validateJson(string $json): bool 
    {
        return json_decode($json) !== null;
    }
    
    /** string length must be >= numChars */
    public static function removeLastCharsFromString(string $input, int $numChars = 1): string 
    {
        if ($numChars > mb_strlen($input)) {
            throw new \InvalidArgumentException("Cannot remove $numChars from $input");
        }
        return substr($input, 0, mb_strlen($input) - $numChars);
    }

    /**
     * converts array to string
     * @param array $arr
     * @param int $level
     * @return string
     */
    public static function arrayWalkToStringRecursive(array $arr, int $level = 0, int $maxLevel = 1000, $newLine = '<br>'): string
    {
        $out = "";
        $tabs = " ";
        for ($i = 0; $i < $level; $i++) {
            $tabs .= " ^"; // use ^ to denote another level
        }
        foreach ($arr as $k => $v) {
            $out .= "$newLine$tabs$k: ";
            if (is_object($v)) {
                $out .= 'object type: '.get_class($v);
            } elseif (is_array($v)) {
                $newLevel = $level + 1;
                if ($newLevel > $maxLevel) {
                    $out .= ' array too deep, quitting';
                } else {
                    $out .= self::arrayWalkToStringRecursive($v, $newLevel, $maxLevel, $newLine);
                }
            } else {
                $out .= (string)$v;
            }
        }
        return $out;
    }

    // VALIDATION FUNCTIONS

    /**
     * Check in for being an integer
     * either type int or the string equivalent of an integer
     * @param $in any type
     * note empty string returns false
     * note 0 or "0" returns true (as it should - no 0 problem as is mentioned by some sites)
     * note 4.00 returns true but "4.00" returns false
     * @return bool
     */
    public static function isInteger($check): bool 
    {
        return (filter_var($check, FILTER_VALIDATE_INT) === false) ? false : true;
    }

    public static function isWholeNumber($check): bool 
    {
        return (!self::isInteger($check) || $check < 0) ? false : true;
    }

    /**
     * checks if string is blank or null
     * this can be helpful for validating required form fields
     * @param string $check
     * @return bool
     */
    public static function isBlankOrNull($check, $trim=true): bool 
    {
        if($trim) {
            $check = trim($check);
        }
        return (mb_strlen($check) == 0 || is_null($check));
    }

    /**
     * checks if string is blank or zero
     * this can be helpful for validating numeric/integer form fields
     * @param string $check
     * @return bool
     */
    public static function isBlankOrZero($check, $trim=true): bool 
    {
        if($trim) {
            $check = trim($check);
        }
        return (mb_strlen($check) == 0 || $check == 0);
    }

    /**
     * checks if string is a positive integer
     * @param string $check
     * @return bool
     */
    public static function isPositiveInteger($check): bool 
    {
        return (self::isInteger($check) && $check > 0);
    }


    public static function isNumericPositive($check): bool 
    {
        if (!is_numeric($check) || $check <= 0) {
            return false;
        }
        return true;
    }

    public static function isDigit($check): bool
    {
        if (mb_strlen($check) != 1 || !self::isInteger($check)) {
            return false;
        }
        return true;
    }

    public static function isEmail($check) 
    {
        return filter_var($check, FILTER_VALIDATE_EMAIL);
    }
    // END VALIDATION FUNCTIONS
}
