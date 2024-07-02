<?php

use Illuminate\Support\Facades\Route;

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::group(['namespace'=>'App\Http\Controllers\Api'], function(){

    Route::any('/login','LoginController@login');
    Route::any('/wallet','LoginController@wallet');
    Route::any('/get_games','LoginController@get_games');
    Route::any('/create_game','LoginController@create_game');
    Route::any("/save_wallet","LoginController@save_wallet");
    Route::any("/register_to_gamers",'LoginController@register_to_gamers');
    Route::any("/gamers_premium",'LoginController@gamers_premium');
    Route::any("/get_gamers",'LoginController@get_gamers');
    Route::any("/get_gamer_user","LoginController@get_gamer_user");
    Route::any("update_score","LoginController@update_score");
    Route::any("/update_date","LoginController@update_date");
    Route::any("/get_now_time_utc","LoginController@get_now_time_utc");
    Route::any("/upload_image","LoginController@upload_image");
    Route::any("/save_user","LoginController@save_user");
    Route::any("/get_user_info","LoginController@get_user_info");
    Route::any("/login_admin","AdminFonction@login");
    Route::any("/get_all_users","AdminFonction@get_all_users");
    Route::any("/get_games_admin","AdminFonction@get_games_admin");
    Route::any("/create_user_admin","AdminFonction@create_user_admin");
    Route::any("/upload_image_admin","AdminFonction@upload_image_admin");
    Route::any("/creates_game","LoginController@creates_game");
    Route::any("/pay_gamers","AdminFonction@pay_gamers");
    Route::any("/get_cash_out","AdminFonction@get_cash_out");
    Route::any("/get_pay_all","AdminFonction@get_pay_all");
    Route::any("/validate_pay_out","AdminFonction@validate_pay_out");
    Route::any("/ban_user","AdminFonction@ban_user");
    Route::any("/get_pay_history","LoginController@get_pay_history");
    Route::any("/get_pay_out","LoginController@get_pay_out");
    Route::any("/delete_account","LoginController@delete_account");
    Route::any("/rate_app","LoginController@rate_app");
    Route::any("/set_or_edit_update_status","AdminFonction@set_or_edit_update_status");
    Route::any("/check_update_availability","LoginController@check_update_availability");


    /*
    Route::any('/get_profile','LoginController@get_profile')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/update_profile','LoginController@update_profile')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/bind_fcmtoken','LoginController@bind_fcmtoken')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/contact','LoginController@contact')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/upload_photo','LoginController@upload_photo')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/send_notice','LoginController@send_notice')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/get_rtc_token','AccessTokenController@get_rtc_token')->middleware('App\Http\Middleware\UserCheck');
    Route::any('/send_notice_test','LoginController@send_notice_test');*/

});

