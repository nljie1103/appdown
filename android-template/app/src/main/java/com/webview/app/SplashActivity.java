package com.webview.app;

import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;

import androidx.appcompat.app.AppCompatActivity;

import org.json.JSONObject;

import java.io.InputStream;
import java.nio.charset.StandardCharsets;

public class SplashActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        boolean enableSplash = true;
        int duration = 2000;

        try {
            InputStream is = getAssets().open("config.json");
            byte[] buf = new byte[is.available()];
            is.read(buf);
            is.close();
            JSONObject config = new JSONObject(new String(buf, StandardCharsets.UTF_8));
            enableSplash = config.optBoolean("enable_splash", true);
            duration = config.optInt("splash_duration", 2000);
        } catch (Exception ignored) {}

        if (!enableSplash) {
            startMain();
            return;
        }

        setContentView(R.layout.activity_splash);

        new Handler(Looper.getMainLooper()).postDelayed(this::startMain, duration);
    }

    private void startMain() {
        startActivity(new Intent(this, MainActivity.class));
        finish();
        overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out);
    }
}
