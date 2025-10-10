<?php

declare(strict_types=1);

namespace TgkwAdc\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class SystemPermission extends AbstractAnnotation
{
    public string $module = ''; // 管理后台:系统设置:角色管理

    public string $action = ''; // 查看、增加、修改、删除

    public string $icon = '';

    public string $menu_type = '1'; // 1展示，0归类

    public string $url_type = 'path'; // 	URL类别(path, frame_url, target_url)

    public string $alias = ''; // 路由别名

    public string $url = ''; // 页面路径

    public string $param = ''; // 路由参数

    public string $sort = '0'; // 排序，越大越前，一级菜单千进位，二级菜单百进位，默认0

    public string $status = '1'; // 状态，0和1，默认1

    public string $app = ''; // 微前端提供者

    public function __construct(
        string $module = '',
        string $action = '',
        string $icon = '',
        string $menu_type = '1',
        string $url_type = 'path',
        string $alias = '',
        string $url = '',
        string $param = '',
        string $sort = '0',
        string $status = '1',
        string $app = ''
    ) {
        $this->module = $module;
        $this->action = $action;
        $this->icon = $icon;
        $this->menu_type = $menu_type;
        $this->url_type = $url_type;
        $this->alias = $alias;
        $this->url = $url;
        $this->param = $param;
        $this->sort = $sort;
        $this->status = $status;
        $this->app = $app;
    }
}
