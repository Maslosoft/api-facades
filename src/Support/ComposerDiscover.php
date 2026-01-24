<?php

namespace Maslosoft\ApiFacades\Support;

class ComposerDiscover
{
	public function discover(string $namespace): string
	{
		// TODO: Discover project namespace folder location based on PSR and namespace
		// For example if composer contains PSR-4:
		//     "autoload": {
		//        "psr-4": {
		//            "Acme\\Project\\": "src/"
		//        },
		// And API namespace is Acme\Project\Api, the path should point to:
		// /project-root/src/Api
		// If namespace of API does not match any autoload, throw exception

		return '';
	}
}