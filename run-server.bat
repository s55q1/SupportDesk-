@echo off
cd /d "%~dp0"
set PHP_BIN=php
where php >nul 2>nul
if errorlevel 1 (
  if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
  )
)
echo يفتح الآن على http://localhost:8000
start "" "http://localhost:8000/pages/login.php"
"%PHP_BIN%" -S localhost:8000 -t "%~dp0"
if errorlevel 1 (
  echo.
  echo PHP غير موجود او حدث خطأ في تشغيل الخادم.
  echo تأكد من تثبيت PHP واضافته الى PATH ثم اعد تشغيل هذا الملف.
  echo سيتم فتح النسخة الثابتة في المتصفح المحلي لعرض العرض التوضيحي.
  start "" "%~dp0index.html"
  pause
)
