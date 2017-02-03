<?php

Route::group(['prefix' => 'api/v1/app/{appName}', 'middleware' => ['multiple-app-support', 'the-shop.requestLogger']], function () {

    // Define a group of APIs that require auth (we use JWT Auth for token authorization)
    Route::group(['middleware' => ['jwt.auth', 'jwt.refresh', 'acl']], function () {
        Route::get('projects/{id}/uploads', 'TheShop\Projects\Controllers\FileUploadController@getProjectUploads');
        Route::put('projects/{id}/makeReservation', 'TheShop\Projects\Controllers\ReservationController@make');
        Route::put('projects/{id}/acceptReservation', 'TheShop\Projects\Controllers\ReservationController@accept');
        Route::put('projects/{id}/declineReservation', 'TheShop\Projects\Controllers\ReservationController@decline');
        Route::post('upload', 'TheShop\Projects\Controllers\FileUploadController@uploadFile');
        Route::get('testing', function () {
            return 'Hello World!';
        });
    });
});
