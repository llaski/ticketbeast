<?php

namespace Tests\Feature;

use App\Invitation;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcceptInvitationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function viewingAnUnusedInvitation()
    {
        $invitation = factory(Invitation::class)->create([
            'code' => 'ABC123',
            'user_id' => null,
        ]);

        $response = $this->get('/invitations/ABC123');

        $response->assertStatus(200);
        $response->assertViewIs('invitations.show');
        $this->assertTrue($response->data('invitation')->is($invitation));
    }

    /** @test */
    public function viewingAUsedInvitation()
    {
        $invitation = factory(Invitation::class)->create([
            'code' => 'ABC123',
            'user_id' => factory(User::class)->create(),
        ]);

        $response = $this->get('/invitations/ABC123');

        $response->assertStatus(404);
    }

    /** @test */
    public function viewingAnInvitationThatDoesNotExist()
    {
        $response = $this->get('/invitations/ABC123');

        $response->assertStatus(404);
    }

    /** @test */
    public function registeringWithAValidInvitationCode()
    {
        $this->weh();
        $invitation = factory(Invitation::class)->create([
            'code' => 'ABC123',
            'user_id' => null,
        ]);

        $response = $this->post('/register', [
            'email' => 'john@example.com',
            'password' => 'secret',
            'invitation_code' => 'ABC123',
        ]);

        $response->assertRedirect('/backstage/concerts');

        $this->assertEquals(1, User::count());

        tap(User::first(), function ($user) use ($invitation) {
            $this->assertEquals('john@example.com', $user->email);
            $this->assertTrue(Hash::check('secret', $user->password));
            $this->assertTrue($invitation->fresh()->user->is($user));
            $this->assertAuthenticatedAs($user);
        });
    }

    /** @test */
    public function registeringWithAUsedInvitation()
    {
        $invitation = factory(Invitation::class)->create([
            'code' => 'ABC123',
            'user_id' => factory(User::class)->create(),
        ]);
        $this->assertEquals(1, User::count());

        $response = $this->post('/register', [
            'email' => 'john@example.com',
            'password' => 'secret',
            'invitation_code' => 'ABC123',
        ]);

        $response->assertStatus(404);
        $this->assertEquals(1, User::count());
    }

    /** @test */
    public function registeringWithAInvitationCodeThatDoesNotExist()
    {
        $response = $this->post('/register', [
            'email' => 'john@example.com',
            'password' => 'secret',
            'invitation_code' => 'ABC123',
        ]);

        $response->assertStatus(404);
        $this->assertEquals(0, User::count());
    }

}
