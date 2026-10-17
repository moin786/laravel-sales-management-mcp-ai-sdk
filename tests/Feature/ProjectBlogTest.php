<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guests are redirected from the project blog', function () {
    $this->get(route('project-blog'))->assertRedirect(route('login'));
});

test('an authenticated user can view the project blog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('project-blog'))
        ->assertOk();
});
