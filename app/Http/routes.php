<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// All API calls are prefixed with "api", and we're defining version of API "v1"
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This route group applies the "JWTAuth" middleware group to every route
| except for login and signup.
|
*/
Route::group(['prefix' => 'api/v1/app/{appName}', 'middleware' => ['multiple-app-support', 'the-shop.requestLogger']], function () {
    // API calls we allow without token authorization
    Route::post('register', 'ProfileController@store');
    Route::post('login', 'ProfileController@login');

    // Define a group of APIs that require auth (we use JWT Auth for token authorization)
    Route::group(['middleware' => ['jwt.auth', 'jwt.refresh', 'acl']], function () {
        Route::put('profiles/changePassword', 'ProfileController@changePassword');
        Route::get('profiles/{id}/performance', 'ProfileController@getPerformance');
        Route::resource('profiles', 'ProfileController');
        Route::resource('validations', 'ValidationController');
        Route::get('projects/{id}/uploads', 'FileUploadController@getProjectUploads');
        Route::put('projects/{id}/makeReservation', 'ReservationController@makeProjectReservation');
        Route::put('projects/{id}/acceptReservation', 'ReservationController@acceptProject');
        Route::put('projects/{id}/declineReservation', 'ReservationController@declineProject');
        Route::put('tasks/{id}/makeReservation', 'ReservationController@makeTaskReservation')->middleware(['adapters']);
        Route::put('tasks/{id}/acceptReservation', 'ReservationController@acceptTask')->middleware(['adapters']);
        Route::put('tasks/{id}/declineReservation', 'ReservationController@declineTask')->middleware(['adapters']);
        Route::get('database/listCollections', 'DatabaseController@listCollections');
        Route::post('slack/message', 'SlackController@sendMessage');
        Route::get('slack/users', 'SlackController@getUsers');
        Route::get('slack/channels', 'SlackController@getChannels');
        Route::post('trello/board', 'TrelloController@createBoard');
        Route::get('trello/boards', 'TrelloController@getBoardIds');
        Route::get('trello/board/{id}/members', 'TrelloController@getMemberIds');
        Route::post('trello/board/{id}/list', 'TrelloController@createList');
        Route::get('trello/board/{id}/lists', 'TrelloController@getListIds');
        Route::put('trello/board/{boardId}/list/{id}/ticket', 'TrelloController@createTicket');
        Route::get('trello/board/{boardId}/list/{id}/tickets', 'TrelloController@getTicketIds');
        Route::put('trello/board/{boardId}/ticket/{ticketId}/member/{memberId}/add', 'TrelloController@assignMember');
        Route::put('trello/board/{boardId}/ticket/{ticketId}/member/{memberId}/remove', 'TrelloController@removeMember');
        Route::put('trello/board/{boardId}/ticket/{id}', 'TrelloController@setDueDate');
        Route::get('configuration', 'ConfigurationController@getConfiguration');
        Route::post('email', 'EmailController@sendEmail');
        Route::post('upload', 'FileUploadController@uploadFile');
        Route::delete('upload/{projectId}/project', 'FileUploadController@deleteProjectUploads');
        Route::get('tasks/{taskId}/performance', 'ProfileController@getPerformancePerTask');


        /**
         * Generic resources routes
         *
         * All of routes use `the-shop.genericResource` middleware so that GenericModel gets injected
         * `$collection` from `resource` route parameter
         */
        Route::get('{resource}', 'GenericResourceController@index')->middleware(['the-shop.genericResource', 'adapters']);
        Route::get('{resource}/archive', 'GenericResourceController@index')->middleware(['the-shop.genericResource', 'adapters']);
        Route::put('{resource}/{id}/archive', 'GenericResourceController@archive')->middleware(['the-shop.genericResource', 'adapters']);
        Route::put('{resource}/{id}/unArchive', 'GenericResourceController@unArchive')->middleware(['the-shop.genericResource', 'adapters']);
        Route::get('{resource}/{id}', 'GenericResourceController@show')->middleware(['the-shop.genericResource', 'adapters']);
        Route::post('{resource}', 'GenericResourceController@store')->middleware(['the-shop.genericResource', 'adapters']);
        Route::put('{resource}/{id}', 'GenericResourceController@update')->middleware(['the-shop.genericResource', 'adapters']);
        Route::delete('{resource}/{id}', 'GenericResourceController@destroy')->middleware(['the-shop.genericResource', 'adapters']);
        Route::post('{resource}/register', 'GenericResourceController@store')->middleware(['the-shop.genericResource', 'adapters']);
    });
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['web']], function () {
    //
});
