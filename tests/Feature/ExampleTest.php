<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_shows_the_public_welcome_portal(): void
    {
        $this->get('/')->assertOk();
    }
}
