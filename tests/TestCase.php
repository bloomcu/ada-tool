<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Models
use DDD\Domain\Base\Subscriptions\Plans\Plan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication,
        RefreshDatabase;

    public function setUp(): void
    {
        parent::setup();

        $this->seedFreePlan();
    }

    /**
     * Production always has a non-buyable "free" plan seeded (SubscriptionPlansSeeder);
     * Organization::plan() falls back to it via withDefault(Plan::free()->toArray()).
     * Seed the baseline row so any response that serialises OrganizationResource
     * (e.g. login/register) doesn't hit Plan::free() === null.
     */
    protected function seedFreePlan(): void
    {
        Plan::create([
            'title' => 'Free Plan',
            'price' => 0,
            'interval' => '',
            'buyable' => false,
            'limits' => ['users' => 1, 'rates' => 100],
            'stripe_price_id' => '',
        ]);
    }
}
