<?php

namespace OrangeHRM\Recruitment\Controller;

use OrangeHRM\Core\Controller\AbstractVueController;
use OrangeHRM\Core\Vue\Component;
use OrangeHRM\Framework\Http\Request;

class InnerStudiosRecruitmentTemplatesController extends AbstractVueController
{
    public function preRender(Request $request): void
    {
        $this->setComponent(new Component('innerstudios-recruitment-templates'));
    }
}
