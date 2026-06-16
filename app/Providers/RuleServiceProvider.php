<?php

namespace App\Providers;

use Whis\Providers\ServiceProvider;
use Whis\Validation\Rule;

class RuleServiceProvider implements ServiceProvider
{
    public function registerServices(){
        Rule::loadDefaultRules();
    }
}
