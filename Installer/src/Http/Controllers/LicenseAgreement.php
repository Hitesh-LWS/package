<?php


namespace Faveo\Installer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class LicenseAgreement extends Controller
{
    /**
     * check all server-requirements meet or not and display finished view.
     * @param Request $request
     * @return Application|Factory|\Illuminate\Contracts\View\View|RedirectResponse
     *
     */
    public function licenseAgreement(Request $request)
    {
        try {
            $errors=0;
            return \view('installer::license-agreement', compact('errors'));

            $errors = $request->server_requirement_error;
            if ($errors == '0' || $errors == 0) {
                /* all requirements are matched now show license agreement */
                return \view('installer::license-agreement', compact('errors'));
            } else {
                /* error found requirement not fulfill */
                return redirect()->back()->with('error', 'Not getting all server requirement');
            }
        } catch (\Exception $exception) {
            Log::error($exception);
            return redirect()->back()->with(['error' => $exception->getMessage()]);
        }
    }

}
