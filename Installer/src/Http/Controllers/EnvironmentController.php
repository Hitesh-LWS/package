<?php

namespace Faveo\Installer\Http\Controllers;

use Exception;
use Faveo\Installer\Events\EnvironmentSaved;
use Faveo\Installer\Helpers\DatabaseManager;
use Faveo\Installer\Helpers\EnvironmentManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EnvironmentController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $EnvironmentManager;
    protected $DatabaseManager;

    /**
     * @param EnvironmentManager $environmentManager
     * @param DatabaseManager $databaseManager
     */
    public function __construct(EnvironmentManager $environmentManager, DatabaseManager $databaseManager)
    {
        $this->EnvironmentManager = $environmentManager;
        $this->DatabaseManager = $databaseManager;
    }

    /**
     * @param Request $request
     * @return Application|Factory|View|RedirectResponse
     * @throws BindingResolutionException
     *
     */
    public function dbSetup(Request $request)
    {
        $validator = validator()->make(request()->all(), [
            'is_accept' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors());
        }

        /* agreement is not accepted by user  */
        if (!$request->is_accept) {
            return back()->with('error', 'You are not accepted the license.');
        }

        return view('installer::database-setup');
    }

    /**
     * Processes the newly saved environment configuration (Form Wizard).
     * @param Request $request
     * @param Redirector $redirect
     * @return RedirectResponse|string
     * @author Hitesh Kumar <hitesh.kumar@ladybirdweb.com>
     */
    public function saveEnviornmentDetails(Request $request, Redirector $redirect)
    {
        try {
            $validator = Validator::make($request->all(), [
                'database_hostname' => 'required|string|max:50',
                'database_name' => 'required|string|max:50',
                'database_username' => 'required|string|max:50',
                'database_password' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return $redirect->back()->with('error', $validator->errors());
            }

            $connection = 'mysql';
            $dbHost = $request->database_hostname;
            $username = $request->database_username;
            $password = $request->database_password;
            $dbName = $request->database_name;
            $port = $request->database_port;

            /* check database connection is setup or not */
            $dbConnection = $this->checkDatabaseConnection($connection, $dbHost, $port, $dbName, $username, $password);

            if (!$dbConnection['status']) {
                return $redirect->back()->with('error', $dbConnection['message']);
            }

            /* save environment constant in file*/
            $this->EnvironmentManager->saveFileWizard($connection, $dbHost, $port, $dbName, $username, $password, "", "");

            event(new EnvironmentSaved($request));

            /* migrate and seed the data */
            $response = $this->DatabaseManager->migrateAndSeed();

            return app('redirect')->route('LaravelInstaller::register')->with('message', $response);
        } catch (Exception $exception) {
            Log::error($exception);
            return $exception->getMessage();
        }
    }

    /**
     * TODO: We can remove this code if PR will be merged: https://github.com/RachidLaasri/LaravelInstaller/pull/162
     * Validate database connection with user credentials (Form Wizard).
     *
     * @param Request $request
     * @return array
     */
    private function checkDatabaseConnection($connection, $host, $port, $database, $dbusername, $dbpassword)
    {

        $settings = config("database.connections.$connection");

        if ($host == 'localhost' || $host == 'localhost:8000') {
            $host = '127.0.0.1';
        }

        config([
            'database' => [
                'default' => $connection,
                'connections' => [
                    $connection => array_merge($settings, [
                        'driver' => $connection,
                        'host' => $host,
                        'port' => $port,
                        'database' => $database,
                        'username' => $dbusername,
                        'password' => $dbpassword,
                    ]),
                ],
            ],
        ]);

        DB::purge();

        try {
            DB::connection()->getPdo();
            return ['status' => true,
                'message' => 'Connection Setup Successfully'];
        } catch (Exception $e) {
            return ['status' => false,
                'message' => $e->getMessage()];
        }
    }
}
