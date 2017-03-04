<?php

use App\OrderConfirmationNumber;

class OrderConfirmationNumberTest extends TestCase
{
    /** @test */
    function confirmation_numbers_must_be_16_characters_long()
    {
        $confirmationNumber = (new OrderConfirmationNumber)->generate();

        $this->assertEquals(16, strlen($confirmationNumber));
    }

    /** @test */
    function confirmation_numbers_can_only_container_uppercase_letters_and_numbers()
    {
        $confirmationNumber = (new OrderConfirmationNumber)->generate();

        $this->assertRegExp('/^[A-Z0-9]+$/', $confirmationNumber);
    }

    /** @test */
    function confirmation_numbers_can_not_contain_ambiguous_characters()
    {
        $confirmationNumber = (new OrderConfirmationNumber)->generate();

        $this->assertFalse(strpos($confirmationNumber, 'I'));
        $this->assertFalse(strpos($confirmationNumber, '1'));
        $this->assertFalse(strpos($confirmationNumber, '0'));
        $this->assertFalse(strpos($confirmationNumber, 'O'));
    }

    /** @test */
    function confirmation_numbers_must_be_unique()
    {
        $confirmationNumbers = collect(range(1, 50))->map(function() {
            return (new OrderConfirmationNumber)->generate();
        });

        $this->assertCount(50, $confirmationNumbers->unique());
    }
}