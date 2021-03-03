<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeWsdl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:wsdl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates all Wsdl ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->copy_file('wsdl/essauth.wsdl');
        $this->copy_file('wsdl/essclient.wsdl');
        $this->copy_file('wsdl/esscontract.wsdl');
        $this->copy_file('wsdl/essuser.wsdl');
        $this->copy_file('wsdl/index.html');

        $this->copy_file('xsd/defaults.xsd');
        $this->copy_file('xsd/essauth.xsd');
        $this->copy_file('xsd/essclient.xsd');
        $this->copy_file('xsd/esscontract.xsd');
        $this->copy_file('xsd/essuser.xsd');
        $this->copy_file('xsd/index.html');

        $this->copy_file('v2/wsdl/essauth.wsdl');
        $this->copy_file('v2/wsdl/essclient.wsdl');
        $this->copy_file('v2/wsdl/esscontract.wsdl');
        $this->copy_file('v2/wsdl/essuser.wsdl');
        $this->copy_file('v2/wsdl/index.html');

        $this->copy_file('v2/xsd/defaults.xsd');
        $this->copy_file('v2/xsd/essauth.xsd');
        $this->copy_file('v2/xsd/essclient.xsd');
        $this->copy_file('v2/xsd/esscontract.xsd');
        $this->copy_file('v2/xsd/essuser.xsd');
        $this->copy_file('v2/xsd/index.html');
    }

    protected function getStub($name)
    {
        return file_get_contents(resource_path("stubs/soap_files/$name"));
    }

    protected function copy_file($name)
    {
        $modelTemplate = str_replace(
            ['{{name_space}}'],
            [config('general.soap_namespace')],
            $this->getStub($name)
        );

        $file = public_path("api/{$name}");
        $folder = dirname($file);

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        file_put_contents($file, $modelTemplate);
    }

}
