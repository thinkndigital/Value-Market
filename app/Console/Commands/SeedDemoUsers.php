<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * One-shot orchestrator over demo:create-seller / demo:create-affiliate / demo:create-delivery-boy /
 * demo:create-wholesaler with fixed, documented credentials, so a single command run produces all four demo
 * accounts plus a clean summary of exactly how to log in to each dashboard - no need to invoke and remember
 * four separate commands' worth of options.
 *
 * Each of the underlying commands validates its own mobile number as unique before creating anything, so
 * running this twice fails cleanly on the second run (an "already taken" validation error per account)
 * rather than silently duplicating or corrupting existing demo data.
 */
class SeedDemoUsers extends Command
{
    protected $signature = 'demo:seed-all {--password= : Password to use for all four accounts (default: Demo@12345)}';

    protected $description = 'Create demo Seller, Affiliate, Delivery Boy, and Wholesaler accounts (with placeholder images and sample data) in one step, and print login links for every dashboard.';

    private const DEFAULT_PASSWORD = 'Demo@12345';

    public function handle(): int
    {
        $password = $this->option('password') ?: self::DEFAULT_PASSWORD;

        $accounts = [
            ['role' => 'Seller', 'command' => 'demo:create-seller', 'username' => 'Demo Seller', 'mobile' => '9990000001', 'loginPath' => '/seller/login', 'extraOptions' => ['--store-name' => 'Demo Store', '--email' => '']],
            ['role' => 'Affiliate', 'command' => 'demo:create-affiliate', 'username' => 'Demo Affiliate', 'mobile' => '9990000002', 'loginPath' => '/affiliate/login', 'extraOptions' => []],
            ['role' => 'Delivery Boy', 'command' => 'demo:create-delivery-boy', 'username' => 'Demo Delivery Boy', 'mobile' => '9990000003', 'loginPath' => '/delivery_boy/login', 'extraOptions' => []],
            ['role' => 'Wholesaler', 'command' => 'demo:create-wholesaler', 'username' => 'Demo Wholesaler', 'mobile' => '9990000004', 'loginPath' => '/wholesaler/login', 'extraOptions' => ['--business-name' => 'Demo Wholesale Co']],
        ];

        $results = [];

        foreach ($accounts as $account) {
            $this->info("=== Creating demo {$account['role']} ===");

            $exitCode = $this->call($account['command'], array_merge([
                '--username' => $account['username'],
                '--mobile' => $account['mobile'],
                '--password' => $password,
            ], $account['extraOptions']));

            $results[] = [
                'role' => $account['role'],
                'mobile' => $account['mobile'],
                'loginUrl' => rtrim(url('/'), '/') . $account['loginPath'],
                'created' => $exitCode === self::SUCCESS,
            ];
        }

        $this->newLine();
        $this->info('=== Login links ===');
        $this->table(
            ['Panel', 'URL', 'Mobile', 'Password', 'Status'],
            array_map(fn($r) => [
                $r['role'],
                $r['loginUrl'],
                $r['mobile'],
                $password,
                $r['created'] ? 'created' : 'FAILED - see error above (mobile may already be in use)',
            ], $results)
        );

        $anyFailed = collect($results)->contains(fn($r) => !$r['created']);

        return $anyFailed ? self::FAILURE : self::SUCCESS;
    }
}
