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

# 5. 清除快取確保配置生效
php artisan optimize:clear

# 6. 啟動 Apache (在 Dockerfile 中啟動或是由 command 啟動)
apache2-foreground