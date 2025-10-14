<?php

namespace Maslosoft\ApiFacades\Interfaces;


interface Processor
{
	/**
	 * Process value and return different one
	 * @param string|array|mixed $value
	 * @return mixed
	 */
	public function process($value);
}
