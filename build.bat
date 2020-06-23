@echo off

CALL D:\RunTime\php7\runtime set
CLS && CD /d %~dp0

echo 初始化...
call php -f %cd%\build.php

echo.
echo 操作完成,按任意键退出...
pause >nul
