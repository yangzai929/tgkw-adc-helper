<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 机构（租户）菜单注解
 * 用于在类或方法上定义菜单/按钮权限信息.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class OrgPermission extends AbstractAnnotation
{
    public string $parentAccessCode = ''; // 父菜单的唯一标识

    public string $accessCode = ''; // 唯一权限标识

    public string $module = ''; // 菜单模块层级标识 管理后台:系统设置:角色管理   最低层级为名称

    public array $i18nName = []; // 国际化菜单名

    public string $type = 'MENU'; // 菜单类型  BUTTON 按钮  MENU 菜单或页面

    public int $sort = 0; // 排序

    public string $frontRouteAlias = ''; // 前端路由别名   前端路由别名，用于前端路由匹配（必填，前端路由标识）

    // 菜单链接
    // 默认情况下此字段无需填写.
    public string $url = '';

    /*
     * path → Vue Router 正常跳转
       frame_url → 用内嵌 iframe 展示第三方页面
       target_url → window.open() 打开外部链接
       当 本字段值 为 path 时， 如果 url 为空 → 从前端的别名映射表中查找对应路径。 如果 url 不为空，即指定前端路由，例 /user/add;
       当 本字段值 为 frame_url 时，为内嵌iframe打开指定网页;
       当 本字段值 为 target_url 时，为新建窗口打开指定链接;
   */
    public string $urlType = 'path'; // 	URL类别(path, frame_url, target_url)

    public string $redirect = ''; // 子菜单此值为空，如果没有特殊情况，父级路由的 redirect 属性不需要指定，前端应默认指向第一个子路由。

    public int $keepAlive = 0; // 前端是否缓存: 0=否, 1=是

    public int $status = 1; // 显示状态: 1显示，0隐藏   隐藏时  不在菜单显示 但会在配置权限时在权限树中显示
    public int $isEnable = 1; // 是否启用: 0=否, 1=是  不启用时 不在菜单显示 也在权限树中不显示
    public int $needAuth = 1; // 是否需要权限: 0=否, 1=是  默认1 ,某些公共菜单不需要权限校验时 设为0
    public string $method = ''; // 请求方法, 目录时填 #

    public int $showMobile = 1; // 移动端是否显示: 0=否, 1=是  隐藏时  菜单在移动端不显示 但会在PC端配置权限时在权限树中显示

    public string $app = ''; // 微前端提供者

    public string $micro = ''; // 微服务提供者

    public int $appId = 0; // 应用ID (预留字段)

    public bool $syncToMenu = true; // 是否同步到 menus 表

    /** @var string[] 拥有以下任一 accessCode 对应权限时自动具备本接口权限，支持跨控制器/跨微服务 */
    public array $grantedByAccessCode = [];

    public function __construct(
        string $parentAccessCode = '',
        string $module = '',
        array $i18nName = [],
        string $type = 'MENU',
        int $sort = 0,
        string $accessCode = '',
        string $frontRouteAlias = '',
        string $url = '',
        string $urlType = 'path',
        string $redirect = '',
        int $keepAlive = 0,
        int $status = 1,
        int $isEnable = 1,
        int $needAuth = 1,
        string $method = '',
        int $showMobile = 1,
        string $app = '',
        string $micro = '',
        int $appId = 0,
        bool $syncToMenu = true,
        array $grantedByAccessCode = [],
    ) {
        $this->parentAccessCode = $parentAccessCode;
        $this->module = $module;
        $this->i18nName = $i18nName;
        $this->type = $type;
        $this->sort = $sort;
        $this->accessCode = $accessCode;
        $this->frontRouteAlias = $frontRouteAlias;
        $this->url = $url;
        $this->urlType = $urlType;
        $this->redirect = $redirect;
        $this->keepAlive = $keepAlive;
        $this->status = $status;
        $this->isEnable = $isEnable;
        $this->needAuth = $needAuth;
        $this->method = $method;
        $this->showMobile = $showMobile;
        $this->app = $app;
        $this->micro = $micro;
        $this->appId = $appId;
        $this->syncToMenu = $syncToMenu;
        $this->grantedByAccessCode = $grantedByAccessCode;
    }
}
