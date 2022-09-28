<?php

namespace Uuu9\Signature\Services\RollingCurl;

class RollingCurlGroupRequest
{
    private $group = null;

    /**
     * Set group for this request
     *
     * @param group The group to be set
     */
    function setGroup($group)
    {
        if (!($group instanceof RollingCurlGroup))
            throw new \Exception("setGroup: group needs to be of instance RollingCurlGroup");

        $this->group = $group;
    }

    /**
     * Process the request
     *
     *
     */
    function process($output, $info)
    {
        if ($this->group)
            $this->group->process($output, $info, $this);
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        unset($this->group);
        parent::__destruct();
    }
}
