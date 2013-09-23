FileQueue
=========
[![Build Status](https://travis-ci.org/mcpruitt/FileQueue.png?branch=master)](https://travis-ci.org/mcpruitt/FileQueue)

**Note: This project is curntly under heavy development and should not be used!**

A simple queue implementation for [laravel 4](https://github.com/laravel/laravel) that works with the filesystem. This can be used during development or when performance is not a concern (such as interacting with intermittent third party services).

## Installation

Simply add FileQueue to your `composer.json` file.

    "mcpruitt/FileQueue":"dev-master"

You'll then need to run `composer install` to download it and have the class autoloader updated.

After composer has installed FileQueue you will need to add the service provider. Edit `app/config/app.php` and updated the `providers` array. 

    'providers' => array(
      // Existing providers...
      'Mcpruitt\Queue\QueueServiceProvider',
     )

Finally you'll need to add the new queue to your `app/config/queue.php` and add a new entry to your `connections` array specifying the `file` driver. 

    'file' => array(
	  'driver' => 'file'
    )