<div class="wrap">
    <h2><?php echo $title; ?></h2>
    <form method="post">
		<?php echo wp_nonce_field( $ns ); ?>
        <table class="form-table">
            <tbody>
			<?php foreach ( $fields as $id => $field ): ?>
                <tr valign="top">
                    <th scope="row"><?php echo $field['label'] ?></th>
                    <td>
						<?php switch ( $field['type'] ):
							case 'text': ?>
                                <input id="<?php echo $id ?>" type="text" name="<?php echo "{$ns}[$id]" ?>" value="<?php echo $values[ $id ]; ?>" size="<?php echo isset( $field['size'] ) ? $field['size'] : 20; ?>">
                                <br>
								<?php break; ?>
							<?php case 'text group': ?>
                                <fieldset id="<?php echo $id ?>">
									<?php foreach ( $field['options'] as $slug => $item ): ?>
                                        <input type="text" name="<?php echo "{$ns}[$id][$slug]" ?>" value="<?php echo isset( $values[ $id ][ $slug ] ) ? $values[ $id ][ $slug ] : ''; ?>" size="<?php echo isset( $field['size'] ) ? $field['size'] : 20; ?>"> <?php echo $item; ?><br>
                                        <br>
									<?php endforeach; ?>
                                </fieldset>
								<?php break; ?>
							<?php case 'integer': ?>
							<?php case 'number': ?>
                                <input id="<?php echo $id ?>" type="number" name="<?php echo "{$ns}[$id]" ?>" value="<?php echo $values[ $id ]; ?>" <?php if ( isset( $field['min'] ) ): echo 'min="' . $field['min'] . '"'; endif; ?> <?php if ( isset( $field['max'] ) ): echo 'max="' . $field['max'] . '"'; endif; ?> <?php if ( isset( $field['step'] ) ): echo 'step="' . $field['step'] . '"'; endif; ?>>
                                <br>
								<?php break; ?>
							<?php case 'url': ?>
                                <input id="<?php echo $id ?>" type="url" name="<?php echo "{$ns}[$id]" ?>" value="<?php echo $values[ $id ]; ?>" pattern="<?php echo isset( $field['pattern'] ) ? $field['pattern'] : 'https?//.*'; ?>" size="<?php echo isset( $field['size'] ) ? $field['size'] : 20; ?>>
                                <br>
								<?php break; ?>
							<?php case 'email': ?>
                                <input id="<?php echo $id ?>" type="email" name="<?php echo "{$ns}[$id]" ?>" value="<?php echo $values[ $id ]; ?>" pattern="<?php echo isset( $field['pattern'] ) ? $field['pattern'] : '^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$'; ?>" size="<?php echo isset( $field['size'] ) ? $field['size'] : 20; ?>>
                                <br>
								<?php break; ?>
							<?php case 'text area': ?>
                                <textarea id="<?php echo $id ?>" name="<?php echo "{$ns}[$id]" ?>" rows="<?php echo isset( $field['rows'] ) ? $field['rows'] : 2; ?>" cols="<?php echo isset( $field['cols'] ) ? $field['cols'] : 20; ?>"><?php echo $values[ $id ]; ?></textarea>
                                <br>
								<?php break; ?>
							<?php case 'checkbox': ?>
                                <input id="<?php echo $id ?>" type="checkbox" name="<?php echo "{$ns}[$id]" ?>" value="1" <?php checked( $values[ $id ] ); ?>>
								<?php break; ?>
							<?php case 'checkbox group': ?>
                                <fieldset id="<?php echo $id ?>">
									<?php foreach ( $field['options'] as $value => $item ): ?>
                                        <input type="checkbox" name="<?php echo "{$ns}[$id][$value]" ?>" value="<?php echo $value; ?>" <?php echo in_array( $value, $values[ $id ] ) ? 'checked' : '' ?>> <?php echo $item; ?><br>
                                        <br>
									<?php endforeach; ?>
                                </fieldset>
								<?php break; ?>
							<?php case 'select': ?>
                                <select id="<?php echo $id ?>" name="<?php echo "{$ns}[$id]" ?>">
									<?php foreach ( $field['options'] as $value => $item ): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $value === $values[ $id ] ? 'selected' : ''; ?>><?php echo $item; ?></option>
									<?php endforeach; ?>
                                </select>
                                <br>
								<?php break; ?>
							<?php case 'select multiple': ?>
                                <select multiple id="<?php echo $id ?>" name="<?php echo "{$ns}[$id][]" ?>">
									<?php foreach ( $field['options'] as $value => $item ): ?>
                                        <option value="<?php echo $value; ?>" <?php echo isset( $values[ $id ] ) && in_array( $value, $values[ $id ] ) ? 'selected' : ''; ?>><?php echo $item; ?></option>
									<?php endforeach; ?>
                                </select>
                                <br>
								<?php break; ?>
							<?php case 'html': ?>
                                <div id="<?php echo $id ?>"><?php echo $field['html']; ?></div>
								<?php break; ?>
							<?php endswitch; ?>
						<?php if ( isset( $field['description'] ) ): ?>
                            <span class="description"><?php echo $field['description']; ?></span>
						<?php endif; ?>
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
        <p class="submit"><input id="submit" class="button button-primary" type="submit" name="submit" value="<?php _e( 'Save Changes', 'kntnt-parallax-images' ); ?>"></p>
    </form>
</div>
