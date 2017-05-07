# Ghost Meta 1.0

## Summary

This plugin provides functionality to work with a `_ghost_meta` custom field value and be able to function with it as if it were the same as `get_post_meta()` using `get_ghost_meta()`.

It can hold a ton of extra data which you may not want to clutter up the `wp_postmeta` table with. It also integrates with the core WordPress Metadata API to make it easy for other plugins to interact with it.
 
It stores all ghost meta values into one meta key, which saves the number of meta records you may otherwise unnecessarily need to store.

## Example use-case

If you have millions of posts to import into your app or site, but you don't need to reference the custom fields in filtering or searches -- this can really save you in terms of speed and efficiency.

If you're using ElasticPress, this can really help you ensure that your ghost meta values will be indexable by Elasticsearch like regular custom fields.

So what could have easily been 15 million postmeta rows for 15 custom fields on 1 million posts -- is now just 1 million postmeta rows with serialized data stored in each.

## ElasticPress Integration

Ghost Meta comes with built-in integration with ElasticPress, so when it indexes your posts it will index your ghost meta just like it would regular custom fields.

Try it out, use `'ep_integrate' => true` with your WP_Query calls and you'll still get to use `'meta_query'` as you would expect it to work, and it'll be glorious without the mess in your database.

## API

### Get ghost meta

`$meta_value = get_ghost_meta( $object_id, $meta_key, $single );`

### Add ghost meta

`add_ghost_meta( $object_id, $meta_key, $meta_value, $unique );`

### Update ghost meta

`update_ghost_meta( $object_id, $meta_key, $meta_value, $prev_value );`

### Delete ghost meta

`delete_ghost_meta( $object_id, $meta_key, $meta_value, $delete_all );`

### Specifying meta type

By default, ghost meta uses `post` as the meta type. However, this can be changed using dot-syntax in `$meta_key`.

For example, you can use `user.my_custom_field` to interact with the `my_custom_field` meta key on the `user` meta type.

### Create custom ghost meta-like functionality

Extend Ghost_Meta and define your own meta type and meta key to store the data in, the code will do the rest. It may help you further separate multiple data stores as needed.

```php
// Load My Custom Ghost Meta
add_action( 'plugins_loaded', array( 'My_Custom_Ghost_Meta', 'get_instance' ), 11 );

class My_Custom_Ghost_Meta extends Ghost_Meta {

	/**
	 * Custom meta type for Ghost meta.
	 *
	 * @param string
	 */
	public $meta_type = 'my_custom_ghost';

	/**
	 * Custom meta key for Ghost meta storage.
	 *
	 * @param string
	 */
	public $meta_key = '_my_custom_ghost_meta';

}
```

Example usage: `$meta_value = get_metadata( 'my_custom_ghost', $object_id, $meta_key, $single );`