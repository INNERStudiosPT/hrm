<?php

namespace OrangeHRM\Core\Controller;

use OrangeHRM\Core\Vue\Component;
use OrangeHRM\Framework\Http\Request;

class InnerStudiosNotificationsController extends AbstractVueController
{
    public function preRender(Request $request): void
    {
        $this->setComponent(new Component('innerstudios-notifications'));
    }
}
