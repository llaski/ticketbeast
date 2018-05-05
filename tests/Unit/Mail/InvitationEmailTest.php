<?php

namespace Tests\Unit\Mail;

use App\Invitation;
use App\Mail\InvitationEmail;
use Tests\TestCase;

class InvitationEmailTest extends TestCase
{
    /** @test */
    public function emailContainsALinkToAcceptTheInvitation()
    {
        $invitation = factory(Invitation::class)->make([
            'email' => 'john@example.com',
            'code' => '1234',
        ]);

        $email = new InvitationEmail($invitation);

        $this->assertContains(url('/invitations/1234'), $email->render());
    }

    /** @test */
    public function emailHasTheCorrectSubject()
    {
        $invitation = factory(Invitation::class)->make([
            'email' => 'john@example.com',
            'code' => '1234',
        ]);

        $email = new InvitationEmail($invitation);

        $this->assertEquals("You're invited to join TicketBeast!", $email->build()->subject);
    }

}
