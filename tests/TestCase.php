<?php

use App\Models\Store;
use App\Models\User;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected User $owner;
    protected User $user;
    protected Store $store;

    public function setUp(): void
    {
        parent::setUp();

        $this->store = new Store([
            'context' => 'stores/test123',
            'access_token' => 'test access token',
        ]);
        $this->store->save();

        $this->owner = new User([
            'username' => 'Store Owner',
            'email' => 'owner@store.com',
            'role' => 'owner',
            'bigcommerce_user_id' => 1,
            'store_id' => $this->store->id
        ]);
        $this->owner->save();

        $this->user = new User([
            'username' => 'Store user',
            'email' => 'john@doe.com',
            'role' => 'user',
            'bigcommerce_user_id' => 2,
            'store_id' => $this->store->id
        ]);
        $this->user->save();
    }


    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }
}
