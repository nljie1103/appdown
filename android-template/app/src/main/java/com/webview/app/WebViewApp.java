package com.webview.app;

import android.app.Application;
import android.webkit.CookieManager;

public class WebViewApp extends Application {
    @Override
    public void onCreate() {
        super.onCreate();
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
    }
}
