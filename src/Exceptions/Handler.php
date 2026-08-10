<?php
namespace Simcify\Exceptions;

use Exception;
use Pecee\SimpleRouter\Handlers\IExceptionHandler;
use Pecee\Http\Request;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\Http\Middleware\Exceptions\TokenMismatchException;

class Handler implements IExceptionHandler {

    /**
     * Handle an error that occurs when routing has began
     * 
     * @param   \Pecee\Http\Request $request
     * @param   \Exception          $error
     * @return  void
     */
	public function handleError(Request $request, Exception $error): void {
        /* The router will throw the NotFoundHttpException on 404 */
        if($error instanceof NotFoundHttpException) {

            $request->setRewriteUrl('/'. url('/404'));

        }

		throw $error;

	}

}
