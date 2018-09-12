<?php
/**
 * Created by Daniel Mellum <mellum@gmail.com>
 * Date: 9/7/2018
 * Time: 10:53 PM
 */

//Lets find some prime numbers.
for ($i = 1; $i <= 1000000; $i++) {
    if (isPrime($i)) {
        $primes[] = $i;
    }
}
echo "Found ".count($primes)." prime numbers! \n";


/**
 * used for stress test.
 *
 * https://stackoverflow.com/a/16763365/4824540
 */
function isPrime($num) {
    if($num == 1)
        return false;

    //2 is prime (the only even number that is prime)
    if($num == 2)
        return true;

    /**
     * if the number is divisible by two, then it's not prime and it's no longer
     * needed to check other even numbers
     */
    if($num % 2 == 0) {
        return false;
    }

    /**
     * Checks the odd numbers. If any of them is a factor, then it returns false.
     * The sqrt can be an aproximation, hence just for the sake of
     * security, one rounds it to the next highest integer value.
     */
    $ceil = ceil(sqrt($num));
    for($i = 3; $i <= $ceil; $i = $i + 2) {
        if($num % $i == 0)
            return false;
    }

    return true;
}