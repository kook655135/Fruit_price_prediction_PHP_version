#!/bin/bash

# 1. 安裝套件 (略過安全稽核)
composer install --no-audit

# 2. 發布資源 (如果尚未發布)
if [ ! -f "config/admin.php" ]; then
    php artisan vendor:publish --provider="Encore\Admin\AdminServiceProvider"
    php artisan vendor:publish --tag=laravel-admin-assets --force
fi

# 3. 執行資料庫遷移
php artisan migrate --force

# 4. 自動化建立自定義管理員帳號
# 我們先檢查帳號是否已存在，避免重複建立導致報錯
USER_EXISTS=$(php artisan tinker --execute="echo \Encore\Admin\Auth\Database\Administrator::where('username', 'tjr103-team02')->count();")

if [ "$USER_EXISTS" -eq "0" ]; then
    echo "建立管理員帳號: tjr103-team02..."
    
    # 使用 Tinker 直接寫入，這樣可以自定義密碼且不需要人工輸入
    php artisan tinker --execute="
        \$user = new \Encore\Admin\Auth\Database\Administrator();
        \$user->username = 'tjr103-team02';
        \$user->password = Hash::make('password');
        \$user->name = 'tjr103-team02';
        \$user->save();
        \$user->roles()->save(\Encore\Admin\Auth\Database\Role::first());
    "
    echo "帳號建立成功！"
else
    echo "帳號 tjr103-team02 已存在，跳過建立步驟。"
fi

# 確保角色與權限存在並已指派 (修復 Permission Denied 問題)
echo "檢查並修復管理員權限..."
php artisan tinker --execute="
    \$role = \Encore\Admin\Auth\Database\Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);
    \$permission = \Encore\Admin\Auth\Database\Permission::firstOrCreate(['slug' => '*'], ['name' => 'All permission', 'http_method' => '', 'http_path' => '*']);
    \Encore\Admin\Auth\Database\Permission::firstOrCreate(['slug' => 'dashboard'], ['name' => 'Dashboard', 'http_method' => 'GET', 'http_path' => '/']);
    \Encore\Admin\Auth\Database\Permission::firstOrCreate(['slug' => 'auth.login'], ['name' => 'Login', 'http_method' => '', 'http_path' => '/auth/login']);
    \Encore\Admin\Auth\Database\Permission::firstOrCreate(['slug' => 'auth.setting'], ['name' => 'User setting', 'http_method' => 'GET,PUT', 'http_path' => '/auth/setting']);
    
    if (!\$role->permissions()->where('slug', '*')->exists()) { \$role->permissions()->save(\$permission); }
    
    \$user = \Encore\Admin\Auth\Database\Administrator::where('username', 'tjr103-team02')->first();
    if (\$user && !\$user->roles()->where('slug', 'administrator')->exists()) { \$user->roles()->save(\$role); }
"

# 修正目錄權限 (確保 www-data 可以寫入)
chmod -R 777 storage bootstrap/cache

# 5. 清除快取確保配置生效
php artisan optimize:clear

# 6. 啟動 Apache (在 Dockerfile 中啟動或是由 command 啟動)
apache2-foreground