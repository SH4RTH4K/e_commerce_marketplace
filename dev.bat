@echo off
TITLE E-Commerce Dev Server
color 0A

echo.
echo  ====================================================
echo    E-Commerce Marketplace - Development Mode
echo  ====================================================
echo.
echo  [1] Start Dev Server (auto-compile on save)
echo  [2] Build for Production (then exit)
echo  [3] Deploy: Build + Show files to upload
echo  [4] Exit
echo.
set /p choice="Enter your choice (1-4): "

SET PATH=C:\Program Files\nodejs;%PATH%

IF "%choice%"=="1" goto DEV
IF "%choice%"=="2" goto BUILD
IF "%choice%"=="3" goto DEPLOY
IF "%choice%"=="4" exit

:DEV
echo.
echo  Starting Laravel + Vite dev server...
echo  - Changes to .jsx/.css files will auto-compile instantly
echo  - Open browser at: http://127.0.0.1:8000
echo  - Press Ctrl+C to stop
echo.
start "Laravel Server" cmd /k "php artisan serve"
timeout /t 2 /nobreak >nul
npm run dev
goto END

:BUILD
echo.
echo  Building production assets...
npm run build
echo.
echo  Done! Files compiled to: public/build/
echo  Upload the public/build/ folder to your cPanel server.
echo.
pause
goto END

:DEPLOY
echo.
echo  Building production assets...
npm run build
echo.
echo ====================================================
echo  UPLOAD THESE FOLDERS/FILES TO cPANEL:
echo ====================================================
echo.
echo  1. public/build/          (compiled JS + CSS)
echo  2. public/build/manifest.json
echo.
echo  DO NOT upload:
echo  - node_modules/    (not needed on server)
echo  - .env             (security risk)
echo.
echo  After uploading, run on cPanel SSH:
echo    php artisan optimize:clear
echo    php artisan config:cache
echo    php artisan route:cache
echo    php artisan view:cache
echo.
pause
goto END

:END
echo.
echo  Exiting...
timeout /t 2 /nobreak >nul
