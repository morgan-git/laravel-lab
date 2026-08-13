<?php

use App\Models\Idea;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->idea = Idea::factory()->for($this->user)->create();
});

it('shows all ideas', function () {
    visit('/ideas')
        ->assertSee($this->idea->description);
});

it('shows a single idea', function () {
    visit('/ideas/'.$this->idea->id)
        ->assertSee($this->idea->description);
});

it('shows an edit form to update an idea', function () {
    visit('/ideas/'.$this->idea->id.'/edit')
        ->assertSee('update');
});

it('creates a new idea', function () {
    // Storage::fake('ideas');

    $file = UploadedFile::fake()->image('avatar.jpg');
    visit('/ideas')
        ->click('@create-idea-button')
        ->fill('title', 'Braised Beef is Brilliant')
        ->click('@idea-status-btn-complete')
        ->fill('description', 'skoopski potato')

        ->fill('@new-link', 'http://laracasts.com')
        ->click('@submit-new-link-button')
        ->fill('@new-link', 'http://laravel.com')
        ->click('@submit-new-link-button')

        ->fill('@new-step', 'step1')
        ->click('@submit-new-step-button')
        ->fill('@new-step', 'step2')
        ->click('@submit-new-step-button')

        ->attach('image', $file->getPathName())
       // ->wait(100)
        ->click('@save-idea-button')

        ->assertPathIs('/ideas');
    // ->debug();
    expect(Idea::count())->toBe(2);
    expect($idea = $this->user->ideas()->latest('id')->first())->toMatchArray([
        'title' => 'Braised Beef is Brilliant',
        'description' => 'skoopski potato',
        'state' => 'complete',
        'links' => ['http://laracasts.com', 'http://laravel.com'],
    ]);

    expect($idea->steps)->toHaveCount(2);

});

it('doesn\'t show an edit form to update an idea thats not the viewing user', function () {

    $user2 = User::factory()->create();
    $this->actingAs($user2);

    visit('/ideas/'.$this->idea->id.'/edit')
        ->assertSee('404');

});

it('doesn\'t show an idea to a user thats not the idea owner', function () {
    // Create a second separate user and sign them in to override the beforeEach user
    $user2 = User::factory()->create();
    $this->actingAs($user2);

    // verify can't visit another user's ideas as well
    visit('/ideas/'.$this->idea->id)
        ->assertSee('404');
});
