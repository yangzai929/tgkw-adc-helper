<?php

namespace TgkwAdc\JsonRpc\Public;

interface SystemServiceInterface {

    public function addMenu(array $param): array;
    public function checkAccessPermission(array $param): array;
}