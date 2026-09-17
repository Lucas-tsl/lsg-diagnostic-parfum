<?php
/**
 * Plugin Name:       LSG Diagnostic Parfum
 * Plugin URI:        https://github.com/Lucas-tsl/lsg-diagnostic-parfum
 * Description:       Sélecteur de diagnostic parfum (famille olfactive + note) avec filtrage de produits WooCommerce, bloc Gutenberg pour page de catégorie, page dédiée responsive en 2 colonnes filtrée sans rechargement, couleurs personnalisables, et compatibilité multilingue WPML.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * Author:            Lucas Troteseil
 * Author URI:        https://github.com/Lucas-tsl
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lsg_toolbox
 * Domain Path:       /languages
 *
 * Le text domain "lsg_toolbox" est volontairement conservé (au lieu de
 * "lsg-diagnostic-parfum") pour rester compatible avec les chaînes déjà
 * enregistrées dans WPML String Translation sur le site d'origine
 * (Les Senteurs Gourmandes). Le changer casserait ces traductions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Sécurité : accès direct interdit.
}

if ( ! defined( 'LSG_DIAG_VERSION' ) ) {
    define( 'LSG_DIAG_VERSION', '1.2.1' );
}

/**
 * Slug du parfum affiché par défaut quand aucun n'est encore choisi dans
 * l'URL, pour éviter que la page arrive vide au premier chargement.
 *
 * WPML peut donner un slug DIFFÉRENT au terme traduit selon la langue
 * (ex. "reconfortant" en FR pourrait devenir "comforting" en EN). On ne
 * peut donc pas se contenter d'un seul slug pour toutes les langues : on
 * renseigne ici le slug du terme "réconfortant" pour chaque langue active.
 * Le plus simple reste d'utiliser le MÊME slug dans toutes les langues
 * (ne traduire que le nom affiché du terme, pas son slug) : dans ce cas,
 * un seul filtre WordPress 'lsg_diag_default_parfum_by_lang' n'est même
 * pas nécessaire, la valeur par défaut ci-dessous suffit partout.
 */
if ( ! defined( 'LSG_DIAG_DEFAULT_PARFUM' ) ) {
    define( 'LSG_DIAG_DEFAULT_PARFUM', 'reconfortant' );
}

if ( ! function_exists( 'lsg_diag_default_parfum_by_lang' ) ) {
    function lsg_diag_default_parfum_by_lang() {
        return apply_filters( 'lsg_diag_default_parfum_by_lang', array(
            'fr' => 'reconfortant',
            'en' => 'comforting',
        ) );
    }
}

/**
 * Retourne le slug du parfum par défaut pour la langue WPML actuellement
 * affichée ; se replie sur LSG_DIAG_DEFAULT_PARFUM si la langue courante
 * n'est pas dans la table ci-dessus ou si WPML n'est pas actif.
 */
if ( ! function_exists( 'lsg_diag_get_default_parfum' ) ) {
    function lsg_diag_get_default_parfum() {
        $lang = apply_filters( 'wpml_current_language', null );
        $map  = lsg_diag_default_parfum_by_lang();

        if ( $lang && isset( $map[ $lang ] ) ) {
            return $map[ $lang ];
        }

        return LSG_DIAG_DEFAULT_PARFUM;
    }
}

/**
 * Récupère les termes d'une taxonomie en s'assurant que le filtre de
 * langue WPML s'applique bien. Par défaut, get_terms() passe
 * 'suppress_filters' => true, ce qui DÉSACTIVE le filtre de langue de
 * WPML et fait remonter les termes de TOUTES les langues en même temps
 * (symptôme observé : "Floral" et "floral-en" affichés ensemble dans le
 * même select). On force donc explicitement 'suppress_filters' => false.
 */
if ( ! function_exists( 'lsg_diag_get_terms' ) ) {
    function lsg_diag_get_terms( $taxonomy ) {
        return get_terms( array(
            'taxonomy'         => $taxonomy,
            'hide_empty'       => false,
            'suppress_filters' => false,
        ) );
    }
}

/**
 * =========================================================================
 * Identifiants uniques par instance de widget (bloc ou shortcode), pour
 * pouvoir poser plusieurs fois le shortcode / le bloc sur une même page
 * sans collision d'id HTML (ni de label "for").
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_unique_id' ) ) {
    function lsg_diag_unique_id() {
        static $count = 0;
        $count++;
        return 'lsg-diag-' . $count;
    }
}

/**
 * =========================================================================
 * Couleurs personnalisables (Réglages > Diagnostic Parfum) : fond de la
 * carte, couleur du texte, fond des champs. S'appliquent uniquement au
 * widget de diagnostic (titre, filtres, bouton réinitialiser) — la grille
 * de produits garde le style du thème.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_default_colors' ) ) {
    function lsg_diag_default_colors() {
        return array(
            'bg'        => '#F9F9F9',
            'text'      => '#000000',
            'fields_bg' => '#FFFFFF',
        );
    }
}

if ( ! function_exists( 'lsg_diag_sanitize_colors' ) ) {
    function lsg_diag_sanitize_colors( $input ) {
        $defaults = lsg_diag_default_colors();
        $output   = array();

        foreach ( $defaults as $key => $default ) {
            $value          = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
            $output[ $key ] = $value ? $value : $default;
        }

        return $output;
    }
}

if ( ! function_exists( 'lsg_diag_get_colors' ) ) {
    function lsg_diag_get_colors() {
        return wp_parse_args( get_option( 'lsg_diag_colors', array() ), lsg_diag_default_colors() );
    }
}

add_action( 'admin_init', function () {
    register_setting( 'lsg_diag_settings', 'lsg_diag_colors', array(
        'type'              => 'array',
        'sanitize_callback' => 'lsg_diag_sanitize_colors',
        'default'           => lsg_diag_default_colors(),
    ) );
} );

add_action( 'admin_menu', function () {
    add_options_page(
        lsg_t( 'Diagnostic Parfum', 'Perfume Diagnostic' ),
        lsg_t( 'Diagnostic Parfum', 'Perfume Diagnostic' ),
        'manage_options',
        'lsg-diag-parfum',
        'lsg_diag_render_settings_page'
    );
} );

if ( ! function_exists( 'lsg_diag_render_settings_page' ) ) {
    function lsg_diag_render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $colors = lsg_diag_get_colors();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( lsg_t( 'Diagnostic Parfum — Couleurs', 'Perfume Diagnostic — Colors' ) ); ?></h1>
            <p>
                <?php
                echo esc_html( lsg_t(
                    "Ces couleurs s'appliquent au widget de diagnostic (bloc catégorie et page dédiée) : titre, filtres et bouton Réinitialiser. La grille de produits garde le style de votre thème.",
                    "These colors apply to the diagnostic widget (category block and dedicated page): title, filters and Reset button. The product grid keeps your theme's styling."
                ) );
                ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields( 'lsg_diag_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="lsg_diag_colors_bg"><?php echo esc_html( lsg_t( 'Fond de la carte', 'Card background' ) ); ?></label>
                        </th>
                        <td>
                            <input type="text" class="lsg-diag-color-field" id="lsg_diag_colors_bg" name="lsg_diag_colors[bg]" value="<?php echo esc_attr( $colors['bg'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lsg_diag_colors_text"><?php echo esc_html( lsg_t( 'Couleur du texte', 'Text color' ) ); ?></label>
                        </th>
                        <td>
                            <input type="text" class="lsg-diag-color-field" id="lsg_diag_colors_text" name="lsg_diag_colors[text]" value="<?php echo esc_attr( $colors['text'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lsg_diag_colors_fields_bg"><?php echo esc_html( lsg_t( 'Fond des champs (menus déroulants)', 'Fields background (dropdowns)' ) ); ?></label>
                        </th>
                        <td>
                            <input type="text" class="lsg-diag-color-field" id="lsg_diag_colors_fields_bg" name="lsg_diag_colors[fields_bg]" value="<?php echo esc_attr( $colors['fields_bg'] ); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'settings_page_lsg-diag-parfum' !== $hook ) {
        return;
    }

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );

    wp_add_inline_script(
        'wp-color-picker',
        'jQuery(function($){$(".lsg-diag-color-field").wpColorPicker();});'
    );
} );

/**
 * =========================================================================
 * Enregistrement + enqueue conditionnel du CSS/JS du widget. Appelé depuis
 * le rendu du bloc et du shortcode (donc uniquement sur les pages qui en
 * ont réellement besoin), une seule fois par requête même si le widget
 * apparaît plusieurs fois sur la page.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_enqueue_assets' ) ) {
    function lsg_diag_enqueue_assets() {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        wp_enqueue_style(
            'lsg-diag-parfum',
            plugins_url( 'assets/css/diagnostic-parfum.css', __FILE__ ),
            array(),
            LSG_DIAG_VERSION
        );

        $colors     = lsg_diag_get_colors();
        $inline_css = sprintf(
            '.lsg-diag-root{--lsg-diag-bg:%1$s;--lsg-diag-text:%2$s;--lsg-diag-fields-bg:%3$s;}',
            sanitize_hex_color( $colors['bg'] ) ? $colors['bg'] : '#F9F9F9',
            sanitize_hex_color( $colors['text'] ) ? $colors['text'] : '#000000',
            sanitize_hex_color( $colors['fields_bg'] ) ? $colors['fields_bg'] : '#FFFFFF'
        );
        wp_add_inline_style( 'lsg-diag-parfum', $inline_css );

        wp_enqueue_script(
            'lsg-diag-parfum',
            plugins_url( 'assets/js/diagnostic-parfum.js', __FILE__ ),
            array(),
            LSG_DIAG_VERSION,
            true
        );

        wp_localize_script( 'lsg-diag-parfum', 'lsgDiagSettings', array(
            'restUrl' => esc_url_raw( rest_url( 'lsg-diag/v1/results' ) ),
            // La requête REST part vers /wp-json/... sans le préfixe de langue
            // de l'URL courante : sans cette info, WPML ne peut pas deviner la
            // langue de la page et retombe sur sa langue par défaut (voir
            // lsg_diag_rest_results()).
            'lang'    => apply_filters( 'wpml_current_language', null ),
        ) );
    }
}

/**
 * =========================================================================
 * 1. Vérifie si une note (pa_mini_diag_note) est associée à un parfum
 *    (pa_mini_diag_parfum) donné.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_term_associated_with_parfum' ) ) {
    function lsg_diag_term_associated_with_parfum( $note_slug, $parfum_slug ) {

        $args = array(
            'post_type' => 'product',
            'tax_query' => array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'pa_mini_diag_parfum',
                    'field'    => 'slug',
                    'terms'    => $parfum_slug,
                ),
                array(
                    'taxonomy' => 'pa_mini_diag_note',
                    'field'    => 'slug',
                    'terms'    => $note_slug,
                ),
            ),
        );

        $query = new WP_Query( $args );

        return $query->have_posts();
    }
}

/**
 * =========================================================================
 * 2. Filtre les produits affichés (archive / catégorie) en fonction des
 *    paramètres GET ?diag=1&diag_parfum=...&diag_note=...
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_filter_products_by_attributes' ) ) {
    function lsg_diag_filter_products_by_attributes( $query ) {

        if ( ! isset( $_GET['diag'] ) ) {
            return;
        }

        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $selected_diag_parfum = isset( $_GET['diag_parfum'] ) ? sanitize_text_field( $_GET['diag_parfum'] ) : '';
        $selected_diag_note   = isset( $_GET['diag_note'] ) ? sanitize_text_field( $_GET['diag_note'] ) : '';

        // Si aucun filtre n'est appliqué, ne montrer aucun produit.
        if ( empty( $selected_diag_parfum ) && empty( $selected_diag_note ) ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $tax_query = array();

        if ( $selected_diag_parfum ) {
            $tax_query[] = array(
                'taxonomy' => 'pa_mini_diag_parfum',
                'field'    => 'slug',
                'terms'    => $selected_diag_parfum,
            );

            // Exclure les produits sans note.
            $tax_query[] = array(
                'taxonomy' => 'pa_mini_diag_note',
                'field'    => 'slug',
                'terms'    => array( '' ),
                'operator' => 'NOT IN',
            );

            if ( $selected_diag_note ) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_mini_diag_note',
                    'field'    => 'slug',
                    'terms'    => $selected_diag_note,
                );
            }

            $tax_query['relation'] = 'AND';
        } else {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $query->set( 'tax_query', $tax_query );
    }
}
add_action( 'pre_get_posts', 'lsg_diag_filter_products_by_attributes' );

/**
 * =========================================================================
 * 3. Briques de rendu partagées par le bloc Gutenberg, le shortcode ET
 *    l'API REST utilisée pour le filtrage sans rechargement de page.
 * =========================================================================
 */

/**
 * Liste des <option> du select de note, y compris l'option "Toutes les
 * notes" et le libellé explicite ("— indisponible pour cette famille")
 * sur les notes non associées à la famille actuellement sélectionnée.
 */
if ( ! function_exists( 'lsg_diag_render_note_options_html' ) ) {
    function lsg_diag_render_note_options_html( $note_terms, $selected_parfum, $selected_note ) {
        ob_start();
        ?>
        <option value="" <?php selected( $selected_note, '' ); ?>><?php echo esc_html( lsg_t( 'Toutes les notes', 'All notes' ) ); ?></option>
        <?php if ( ! empty( $note_terms ) && ! is_wp_error( $note_terms ) ) : ?>
            <?php foreach ( $note_terms as $term ) : ?>
                <?php if ( is_object( $term ) ) : ?>
                    <?php
                    $is_disabled = ! lsg_diag_term_associated_with_parfum( $term->slug, $selected_parfum );
                    $label       = $term->name;
                    if ( $is_disabled ) {
                        $label .= ' — ' . lsg_t( 'indisponible pour cette famille', 'unavailable for this family' );
                    }
                    ?>
                    <option
                        value="<?php echo esc_attr( $term->slug ); ?>"
                        <?php selected( $selected_note, $term->slug ); ?>
                        <?php echo $is_disabled ? 'class="disabled-note" disabled' : ''; ?>
                    >
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
}

/**
 * Formulaire des deux filtres (famille + note), commun au bloc et au
 * shortcode. Un vrai <form method="get"> permet au filtrage de fonctionner
 * même sans JavaScript (le bouton "Filtrer" n'est révélé que dans ce cas,
 * via la balise <noscript> ci-dessous).
 */
if ( ! function_exists( 'lsg_diag_render_fields_form' ) ) {
    function lsg_diag_render_fields_form( $uid, $selected_parfum, $selected_note, $parfum_terms, $note_terms ) {
        $parfum_id = $uid . '-parfum';
        $note_id   = $uid . '-note';
        $hint_id   = $uid . '-hint';
        ob_start();
        ?>
        <form class="lsg-diag-form" method="get" action="">
            <input type="hidden" name="diag" value="1" />

            <div class="lsg-diag-fields">
                <!-- Sélection du parfum -->
                <div class="lsg-diag-field-group">
                    <label class="gotu-regular blocks-caps lsg-diag-label" for="<?php echo esc_attr( $parfum_id ); ?>"><?php echo esc_html__( 'I want a trail of perfume', 'lsg_toolbox' ); ?></label>
                    <select class="gotu-regular diag-input" id="<?php echo esc_attr( $parfum_id ); ?>" name="diag_parfum" aria-label="<?php echo esc_attr( lsg_t( 'Choisir une famille de parfum', 'Choose a perfume family' ) ); ?>">
                        <?php if ( ! empty( $parfum_terms ) && ! is_wp_error( $parfum_terms ) ) : ?>
                            <?php foreach ( $parfum_terms as $term ) : ?>
                                <?php if ( is_object( $term ) ) : ?>
                                    <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_parfum, $term->slug ); ?>>
                                        <?php echo esc_html( $term->name ); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Sélection des notes -->
                <div class="lsg-diag-field-group" style="position: relative;">
                    <label class="gotu-regular blocks-caps lsg-diag-label" for="<?php echo esc_attr( $note_id ); ?>"><?php echo esc_html__( 'with a concord of', 'lsg_toolbox' ); ?></label>
                    <select class="gotu-regular diag-input" id="<?php echo esc_attr( $note_id ); ?>" name="diag_note" aria-label="<?php echo esc_attr( lsg_t( 'Choisir une note olfactive', 'Choose a scent note' ) ); ?>">
                        <?php echo lsg_diag_render_note_options_html( $note_terms, $selected_parfum, $selected_note ); ?>
                    </select>
                    <div class="hint-box top" id="<?php echo esc_attr( $hint_id ); ?>" style="top: 35px; left: -82px; display:none;">
                        <?php echo esc_html__( 'Fill those fields to find your perfume!', 'lsg_toolbox' ); ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="lsg-diag-submit"><?php echo esc_html( lsg_t( 'Filtrer', 'Filter' ) ); ?></button>
            <noscript><style>.lsg-diag-submit{display:inline-block !important;}</style></noscript>
        </form>
        <?php
        return ob_get_clean();
    }
}

/**
 * Bouton "Réinitialiser". Toujours présent dans le DOM (masqué via l'attribut
 * HTML "hidden" quand aucun filtre n'est actif) afin que le JS puisse le
 * révéler/masquer après un filtrage AJAX sans avoir à réinjecter de markup.
 */
if ( ! function_exists( 'lsg_diag_render_reset_button' ) ) {
    function lsg_diag_render_reset_button( $uid, $has_active_filter ) {
        ob_start();
        ?>
        <button type="button" id="<?php echo esc_attr( $uid ); ?>-reset" class="lsg-diag-reset" <?php echo $has_active_filter ? '' : 'hidden'; ?>>
            <?php echo esc_html( lsg_t( 'Réinitialiser', 'Reset' ) ); ?>
        </button>
        <?php
        return ob_get_clean();
    }
}

/**
 * Libellé du compteur de résultats ("3 parfums trouvés"), vide si count = 0
 * (le message "Aucun résultat" de la grille suffit, pas besoin des deux).
 */
if ( ! function_exists( 'lsg_diag_count_label' ) ) {
    function lsg_diag_count_label( $count ) {
        if ( ! $count ) {
            return '';
        }
        return sprintf(
            /* translators: %d: nombre de parfums correspondant à la sélection */
            _n( '%d parfum trouvé', '%d parfums trouvés', $count, 'lsg_toolbox' ),
            $count
        );
    }
}

/**
 * Requête produits WooCommerce pour la page dédiée (shortcode) : identique
 * à la logique d'origine, réutilisée par le rendu initial ET par l'API REST
 * pour garantir un résultat strictement identique dans les deux cas.
 */
if ( ! function_exists( 'lsg_diag_build_results' ) ) {
    function lsg_diag_build_results( $parfum_slug, $note_slug ) {
        $items_html    = '';
        $visible_count = 0;

        if ( ! $parfum_slug ) {
            return array( $items_html, $visible_count );
        }

        $tax_query = array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'pa_mini_diag_parfum',
                'field'    => 'slug',
                'terms'    => $parfum_slug,
            ),
            array(
                'taxonomy' => 'pa_mini_diag_note',
                'field'    => 'slug',
                'terms'    => array( '' ),
                'operator' => 'NOT IN',
            ),
        );

        if ( $note_slug ) {
            $tax_query[] = array(
                'taxonomy' => 'pa_mini_diag_note',
                'field'    => 'slug',
                'terms'    => $note_slug,
            );
        }

        $products_query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => $tax_query,
        ) );

        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) :
                $products_query->the_post();
                $product = wc_get_product( get_the_ID() );
                if ( ! $product || ! $product->is_visible() ) {
                    continue;
                }
                $visible_count++;
                ob_start();
                ?>
                <li class="lsg-diag-result-item">
                    <a href="<?php echo esc_url( get_permalink() ); ?>">
                        <?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
                        <h3><?php echo esc_html( $product->get_name() ); ?></h3>
                        <span class="price"><?php echo $product->get_price_html(); ?></span>
                    </a>
                </li>
                <?php
                $items_html .= ob_get_clean();
            endwhile;
        }

        wp_reset_postdata();

        return array( $items_html, $visible_count );
    }
}

/**
 * Grille de résultats (ou message "aucun résultat"), réutilisée par le
 * rendu initial ET par l'API REST.
 */
if ( ! function_exists( 'lsg_diag_render_results_markup' ) ) {
    function lsg_diag_render_results_markup( $items_html, $count ) {
        ob_start();
        if ( $count > 0 ) :
            ?>
            <ul class="lsg-diag-results">
                <?php echo $items_html; ?>
            </ul>
            <?php
        else :
            ?>
            <p class="lsg-diag-no-result"><?php echo esc_html( lsg_t( 'Aucun parfum ne correspond encore à cette combinaison.', 'No perfume matches this combination yet.' ) ); ?></p>
            <?php
        endif;
        return ob_get_clean();
    }
}

/**
 * =========================================================================
 * 3bis. Rendu du sélecteur seul (bloc Gutenberg custom/diag, page de
 *    catégorie) : pas de grille de résultats ici, c'est la boucle produits
 *    native du thème qui est filtrée côté serveur via pre_get_posts, donc
 *    la sélection continue de naviguer vers l'URL filtrée (pas d'AJAX
 *    possible sans dépendre du template de boucle du thème).
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_render_selector' ) ) {
    function lsg_diag_render_selector() {

        $selected_diag_parfum = isset( $_GET['diag_parfum'] ) ? sanitize_text_field( wp_unslash( $_GET['diag_parfum'] ) ) : lsg_diag_get_default_parfum();
        $selected_diag_note   = isset( $_GET['diag_note'] ) ? sanitize_text_field( wp_unslash( $_GET['diag_note'] ) ) : '';
        $has_active_filter    = isset( $_GET['diag_parfum'] ) || isset( $_GET['diag_note'] );

        $parfum_terms = lsg_diag_get_terms( 'pa_mini_diag_parfum' );
        $note_terms   = lsg_diag_get_terms( 'pa_mini_diag_note' );

        $uid = lsg_diag_unique_id();

        lsg_diag_enqueue_assets();

        ob_start();
        ?>
        <div class="lsg-diag-root lsg-diag-wrapper" id="<?php echo esc_attr( $uid ); ?>-widget">
            <div class="custom-diag-block lsg-diag-card gotu-regular">
                <h1 class="gotu-regular h3-like lsg-diag-title"><?php echo esc_html__( 'Find your perfume desire', 'lsg_toolbox' ); ?></h1>

                <?php echo lsg_diag_render_fields_form( $uid, $selected_diag_parfum, $selected_diag_note, $parfum_terms, $note_terms ); ?>

                <?php echo lsg_diag_render_reset_button( $uid, $has_active_filter ); ?>
            </div>

            <div id="<?php echo esc_attr( $uid ); ?>-loading" class="lsg-diag-loading" aria-hidden="true">
                <span class="lsg-diag-spinner"></span>
                <span class="screen-reader-text"><?php echo esc_html( lsg_t( 'Chargement…', 'Loading…' ) ); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Sous-titre affiché en H2, sous le H1, sur la page dédiée. Modifie cette
 * constante pour changer le texte sans toucher au code.
 */
if ( ! defined( 'LSG_DIAG_SUBTITLE_FR' ) ) {
    define( 'LSG_DIAG_SUBTITLE_FR', 'Répondez à ces deux questions pour découvrir le parfum qui vous ressemble.' );
}
if ( ! defined( 'LSG_DIAG_SUBTITLE_EN' ) ) {
    define( 'LSG_DIAG_SUBTITLE_EN', 'Answer these two questions to discover the perfume that suits you.' );
}

/**
 * =========================================================================
 * 3ter. Page de diagnostic complète (shortcode [lsg_diag_parfum]) :
 *    mise en page à 2 colonnes.
 *    - Colonne gauche : H1, H2, les 2 filtres, bouton reset, compteur.
 *    - Colonne droite : loader puis grille de produits filtrés.
 *    Le filtrage se fait via l'API REST du plugin (voir plus bas), sans
 *    recharger la page — repli automatique sur une navigation classique si
 *    la requête échoue ou si JavaScript est désactivé.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_render_diagnostic_page' ) ) {
    function lsg_diag_render_diagnostic_page() {

        $selected_diag_parfum = isset( $_GET['diag_parfum'] ) ? sanitize_text_field( wp_unslash( $_GET['diag_parfum'] ) ) : lsg_diag_get_default_parfum();
        $selected_diag_note   = isset( $_GET['diag_note'] ) ? sanitize_text_field( wp_unslash( $_GET['diag_note'] ) ) : '';
        $has_active_filter    = isset( $_GET['diag_parfum'] ) || isset( $_GET['diag_note'] );

        $parfum_terms = lsg_diag_get_terms( 'pa_mini_diag_parfum' );
        $note_terms   = lsg_diag_get_terms( 'pa_mini_diag_note' );

        list( $items_html, $visible_count ) = lsg_diag_build_results( $selected_diag_parfum, $selected_diag_note );
        $count_label = lsg_diag_count_label( $visible_count );

        $uid = lsg_diag_unique_id();

        lsg_diag_enqueue_assets();

        ob_start();
        ?>
        <div class="lsg-diag-root lsg-diag-layout" id="<?php echo esc_attr( $uid ); ?>-widget">

            <!-- ===================== COLONNE GAUCHE ===================== -->
            <div class="lsg-diag-col-left">
                <div class="lsg-diag-card gotu-regular">
                    <h1 class="gotu-regular h3-like lsg-diag-title"><?php echo esc_html__( 'Find your perfume desire', 'lsg_toolbox' ); ?></h1>
                    <h2 class="inter-regular lsg-diag-subtitle"><?php echo esc_html( lsg_t( LSG_DIAG_SUBTITLE_FR, LSG_DIAG_SUBTITLE_EN ) ); ?></h2>

                    <?php echo lsg_diag_render_fields_form( $uid, $selected_diag_parfum, $selected_diag_note, $parfum_terms, $note_terms ); ?>

                    <?php echo lsg_diag_render_reset_button( $uid, $has_active_filter ); ?>

                    <p class="gotu-regular blocks-caps lsg-diag-count" <?php echo $visible_count > 0 ? '' : 'hidden'; ?>>
                        <?php echo esc_html( $count_label ); ?>
                    </p>
                </div>
            </div>

            <!-- ===================== COLONNE DROITE ===================== -->
            <div class="lsg-diag-col-right">
                <div id="<?php echo esc_attr( $uid ); ?>-loading" class="lsg-diag-loading" aria-hidden="true">
                    <span class="lsg-diag-spinner"></span>
                    <span class="screen-reader-text"><?php echo esc_html( lsg_t( 'Chargement…', 'Loading…' ) ); ?></span>
                </div>

                <div class="lsg-diag-results-wrapper" aria-live="polite">
                    <?php echo lsg_diag_render_results_markup( $items_html, $visible_count ); ?>
                </div>
            </div>

        </div>
        <?php

        return ob_get_clean();
    }
}

/**
 * =========================================================================
 * 3quater. API REST utilisée par le JS pour ne remplacer que la grille de
 *    résultats et le select de notes de la page dédiée, sans recharger la
 *    page. Route publique en lecture seule (mêmes données qu'une navigation
 *    classique sur une page dont les produits sont publiés).
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_rest_results' ) ) {
    function lsg_diag_rest_results( WP_REST_Request $request ) {
        // Remet le contexte de langue WPML de la page d'origine avant toute
        // requête : une requête REST arrive sur /wp-json/... sans le préfixe
        // de langue de l'URL, WPML ne peut donc pas le déduire lui-même et
        // reviendrait sinon à sa langue par défaut (termes ET lsg_t() dans la
        // mauvaise langue).
        $lang = $request->get_param( 'lang' );
        if ( $lang && has_action( 'wpml_switch_language' ) ) {
            do_action( 'wpml_switch_language', $lang );
        }

        $has_parfum_param = null !== $request->get_param( 'diag_parfum' );
        $has_note_param   = null !== $request->get_param( 'diag_note' );

        $selected_diag_parfum = $has_parfum_param ? sanitize_text_field( $request->get_param( 'diag_parfum' ) ) : lsg_diag_get_default_parfum();
        $selected_diag_note   = $has_note_param ? sanitize_text_field( $request->get_param( 'diag_note' ) ) : '';

        $note_terms = lsg_diag_get_terms( 'pa_mini_diag_note' );

        list( $items_html, $visible_count ) = lsg_diag_build_results( $selected_diag_parfum, $selected_diag_note );

        return rest_ensure_response( array(
            'html'            => lsg_diag_render_results_markup( $items_html, $visible_count ),
            'count'           => $visible_count,
            'countLabel'      => lsg_diag_count_label( $visible_count ),
            'noteOptions'     => lsg_diag_render_note_options_html( $note_terms, $selected_diag_parfum, $selected_diag_note ),
            'selectedParfum'  => $selected_diag_parfum,
            'selectedNote'    => $selected_diag_note,
            'hasActiveFilter' => (bool) ( $has_parfum_param || $has_note_param ),
        ) );
    }
}

if ( ! function_exists( 'lsg_diag_register_rest_routes' ) ) {
    function lsg_diag_register_rest_routes() {
        register_rest_route( 'lsg-diag/v1', '/results', array(
            'methods'             => 'GET',
            'callback'            => 'lsg_diag_rest_results',
            'permission_callback' => '__return_true',
            'args'                => array(
                'diag_parfum' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'diag_note'   => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'lang'        => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }
}
add_action( 'rest_api_init', 'lsg_diag_register_rest_routes' );

/**
 * =========================================================================
 * 3quinquies. SEO : titre d'onglet dynamique et balise canonical.
 *    Comme le résultat dépend de paramètres GET, on adapte le <title>
 *    à la sélection en cours et on pointe le canonical vers l'URL propre
 *    de la page (sans paramètres) pour éviter le duplicate content.
 *    (S'applique au chargement initial de la page ; un filtrage AJAX
 *    ultérieur ne réécrit pas le <title>, la balise canonical n'ayant de
 *    toute façon de sens qu'au chargement d'une URL donnée.)
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_page_has_shortcode' ) ) {
    function lsg_diag_page_has_shortcode() {
        return is_singular() && ! empty( $GLOBALS['post'] ) && has_shortcode( $GLOBALS['post']->post_content, 'lsg_diag_parfum' );
    }
}

/**
 * Construit les libellés ("Chaleureux", "Boisé"...) correspondant à la
 * sélection en cours, réutilisés par le titre WordPress natif ET par
 * les filtres de titre de Yoast SEO / RankMath ci-dessous.
 */
if ( ! function_exists( 'lsg_diag_selected_labels' ) ) {
    function lsg_diag_selected_labels() {
        $selected_diag_parfum = isset( $_GET['diag_parfum'] ) ? sanitize_text_field( $_GET['diag_parfum'] ) : lsg_diag_get_default_parfum();
        $selected_diag_note   = isset( $_GET['diag_note'] ) ? sanitize_text_field( $_GET['diag_note'] ) : '';

        $labels = array();

        if ( $selected_diag_parfum ) {
            $term = get_term_by( 'slug', $selected_diag_parfum, 'pa_mini_diag_parfum' );
            if ( $term && ! is_wp_error( $term ) ) {
                $labels[] = $term->name;
            }
        }

        if ( $selected_diag_note ) {
            $term = get_term_by( 'slug', $selected_diag_note, 'pa_mini_diag_note' );
            if ( $term && ! is_wp_error( $term ) ) {
                $labels[] = $term->name;
            }
        }

        return $labels;
    }
}

if ( ! function_exists( 'lsg_diag_document_title_parts' ) ) {
    function lsg_diag_document_title_parts( $parts ) {
        if ( ! lsg_diag_page_has_shortcode() ) {
            return $parts;
        }

        $labels = lsg_diag_selected_labels();

        if ( ! empty( $labels ) ) {
            $parts['title'] = implode( ' & ', $labels ) . ' – ' . $parts['title'];
        }

        return $parts;
    }
}
// Titre natif WordPress : utilisé seulement si aucun plugin SEO ne prend la main avant.
add_filter( 'document_title_parts', 'lsg_diag_document_title_parts' );

/**
 * Yoast SEO définit son propre titre via le filtre 'pre_get_document_title',
 * exécuté AVANT 'document_title_parts' : sans ce filtre dédié, le titre
 * dynamique ci-dessus serait ignoré dès que Yoast est actif.
 */
if ( ! function_exists( 'lsg_diag_seo_plugin_title' ) ) {
    function lsg_diag_seo_plugin_title( $title ) {
        if ( ! lsg_diag_page_has_shortcode() ) {
            return $title;
        }

        $labels = lsg_diag_selected_labels();

        if ( ! empty( $labels ) ) {
            $title = implode( ' & ', $labels ) . ' – ' . $title;
        }

        return $title;
    }
}
add_filter( 'wpseo_title', 'lsg_diag_seo_plugin_title' );          // Yoast SEO
add_filter( 'rank_math/frontend/title', 'lsg_diag_seo_plugin_title' ); // RankMath

if ( ! function_exists( 'lsg_diag_canonical' ) ) {
    function lsg_diag_canonical() {
        // Si Yoast SEO ou RankMath sont actifs, ils gèrent déjà le canonical :
        // on ne fait rien pour éviter une double balise.
        if ( class_exists( 'WPSEO_Frontend' ) || defined( 'RANK_MATH_VERSION' ) ) {
            return;
        }

        if ( ! lsg_diag_page_has_shortcode() ) {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url( get_permalink( $GLOBALS['post']->ID ) ) . '" />' . "\n";
    }
}
add_action( 'wp_head', 'lsg_diag_canonical' );

/**
 * =========================================================================
 * 4. Bloc Gutenberg custom/diag (comportement identique à l'existant :
 *    ne s'affiche que si ?diag est présent dans l'URL — utilisé sur la
 *    page de catégorie de produits).
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_render_custom_block' ) ) {
    function lsg_diag_render_custom_block( $attributes, $content ) {
        if ( isset( $_GET['diag'] ) ) {
            return lsg_diag_render_selector();
        }
        return '';
    }
}

add_action( 'init', function () {
    register_block_type( 'custom/diag', array(
        'render_callback' => 'lsg_diag_render_custom_block',
    ) );
} );

/**
 * =========================================================================
 * 5. Shortcode [lsg_diag_parfum] : affiche TOUJOURS le sélecteur, sans
 *    attendre ?diag dans l'URL. C'est celui-ci qu'il faut utiliser sur
 *    une page WordPress classique dédiée au diagnostic.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_shortcode' ) ) {
    function lsg_diag_shortcode( $atts ) {
        return lsg_diag_render_diagnostic_page();
    }
}
add_shortcode( 'lsg_diag_parfum', 'lsg_diag_shortcode' );
