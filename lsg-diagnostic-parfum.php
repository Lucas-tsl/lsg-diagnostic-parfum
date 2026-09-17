<?php
/**
 * Plugin Name:       LSG Diagnostic Parfum
 * Plugin URI:        https://github.com/Lucas-tsl/lsg-diagnostic-parfum
 * Description:       Sélecteur de diagnostic parfum (famille olfactive + note) avec filtrage de produits WooCommerce, bloc Gutenberg pour page de catégorie, page dédiée responsive en 2 colonnes, et compatibilité multilingue WPML.
 * Version:           1.1.0
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
 * 3. Rendu HTML/JS/CSS du sélecteur de diagnostic (parfum + note).
 *    Fonction commune, réutilisée par le bloc Gutenberg ET par le shortcode.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_render_selector' ) ) {
    function lsg_diag_render_selector() {

        $selected_diag_parfum = isset( $_GET['diag_parfum'] ) ? sanitize_text_field( $_GET['diag_parfum'] ) : lsg_diag_get_default_parfum();
        $selected_diag_note   = isset( $_GET['diag_note'] ) ? sanitize_text_field( $_GET['diag_note'] ) : '';

        $parfum_terms = lsg_diag_get_terms( 'pa_mini_diag_parfum' );
        $note_terms   = lsg_diag_get_terms( 'pa_mini_diag_note' );

        $has_active_filter = isset( $_GET['diag_parfum'] ) || isset( $_GET['diag_note'] );

        ob_start();
        ?>
        <div class="lsg-diag-wrapper">
            <div class="custom-diag-block lsg-diag-card gotu-regular">
                <h1 class="gotu-regular h3-like lsg-diag-title"><?php echo esc_html__( 'Find your perfume desire', 'lsg_toolbox' ); ?></h1>

                <div class="lsg-diag-fields">
                    <!-- Sélection du parfum -->
                    <div class="lsg-diag-field-group">
                        <label class="gotu-regular blocks-caps lsg-diag-label" for="diag_parfum"><?php echo esc_html__( 'I want a trail of perfume', 'lsg_toolbox' ); ?></label>
                        <select class="gotu-regular diag-input" id="diag_parfum" name="diag_parfum" aria-label="<?php echo esc_attr( lsg_t( 'Choisir une famille de parfum', 'Choose a perfume family' ) ); ?>">
                            <?php if ( ! empty( $parfum_terms ) && ! is_wp_error( $parfum_terms ) ) : ?>
                                <?php foreach ( $parfum_terms as $term ) : ?>
                                    <?php if ( is_object( $term ) ) : ?>
                                        <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_diag_parfum, $term->slug ); ?>>
                                            <?php echo esc_html( $term->name ); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Sélection des notes -->
                    <div class="lsg-diag-field-group" style="position: relative;">
                        <label class="gotu-regular blocks-caps lsg-diag-label" for="diag_note"><?php echo esc_html__( 'with a concord of', 'lsg_toolbox' ); ?></label>
                        <select class="gotu-regular diag-input" id="diag_note" name="diag_note" aria-label="<?php echo esc_attr( lsg_t( 'Choisir une note olfactive', 'Choose a scent note' ) ); ?>">
                            <option value="" selected>...</option>
                            <?php if ( ! empty( $note_terms ) && ! is_wp_error( $note_terms ) ) : ?>
                                <?php foreach ( $note_terms as $term ) : ?>
                                    <?php if ( is_object( $term ) ) : ?>
                                        <?php
                                        $is_disabled = ! lsg_diag_term_associated_with_parfum( $term->slug, $selected_diag_parfum );
                                        $class       = $is_disabled ? 'class="disabled-note"' : '';
                                        $disabled    = $is_disabled ? 'disabled' : '';
                                        ?>
                                        <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_diag_note, $term->slug ); ?> <?php echo $class; ?> <?php echo $disabled; ?>>
                                            <?php echo esc_html( $term->name ); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="hint-box top" id="hint-diag" style="top: 35px; left: -82px; display:none;">
                            <?php echo esc_html__( 'Fill those fields to find your perfume!', 'lsg_toolbox' ); ?>
                        </div>
                    </div>
                </div>

                <?php if ( $has_active_filter ) : ?>
                    <button type="button" id="lsg-diag-reset" class="lsg-diag-reset">
                        <?php echo esc_html( lsg_t( 'Réinitialiser', 'Reset' ) ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div id="lsg-diag-loading" class="lsg-diag-loading" aria-hidden="true">
                <span class="lsg-diag-spinner"></span>
            </div>
        </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const parfumSelect   = document.getElementById('diag_parfum');
                const noteSelect     = document.getElementById('diag_note');
                const resetBtn       = document.getElementById('lsg-diag-reset');
                const loadingOverlay = document.getElementById('lsg-diag-loading');

                if (!parfumSelect || !noteSelect) return;

                function showLoading() {
                    if (loadingOverlay) {
                        loadingOverlay.classList.add('is-active');
                        loadingOverlay.setAttribute('aria-hidden', 'false');
                    }
                }

                function updateURL() {
                    const selectedParfum = parfumSelect.value;
                    const selectedNote   = noteSelect.value;

                    let url = new URL(window.location.href);

                    if (selectedParfum) {
                        url.searchParams.set('diag_parfum', selectedParfum);
                    } else {
                        url.searchParams.delete('diag_parfum');
                    }

                    if (selectedNote) {
                        url.searchParams.set('diag_note', selectedNote);
                    } else {
                        url.searchParams.delete('diag_note');
                    }

                    url.searchParams.set('diag', '1');

                    showLoading();
                    window.location.href = url.toString();
                }

                parfumSelect.addEventListener('change', function() {
                    noteSelect.selectedIndex = 0;
                    updateURL();
                });

                noteSelect.addEventListener('change', updateURL);

                if (resetBtn) {
                    resetBtn.addEventListener('click', function() {
                        showLoading();
                        window.location.href = window.location.origin + window.location.pathname;
                    });
                }
            });
        </script>

        <style>
            .lsg-diag-wrapper {
                max-width: 900px;
                margin: 0 auto;
                padding: 0 16px;
            }

            .lsg-diag-card {
                background-color: #F9F9F9;
                border-radius: 8px;
                padding: 40px 32px;
                text-align: center;
            }

            .lsg-diag-title {
                margin-bottom: 28px;
            }

            .lsg-diag-fields {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 32px;
                margin-bottom: 24px;
            }

            .lsg-diag-field-group {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
                min-width: 220px;
            }

            .lsg-diag-label {
                font-size: 0.75em;
                letter-spacing: 0.05em;
                opacity: 0.7;
                margin-bottom: 8px;
            }

            select.diag-input {
                width: 100%;
                min-width: 220px;
                padding: 12px 40px 12px 14px;
                border: none;
                border-bottom: 1px solid #000;
                border-radius: 0;
                background-color: #fff;
                font-size: 15px;
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                cursor: pointer;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23000' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 14px center;
                background-size: 12px 8px;
                transition: border-color 0.2s ease;
            }
            select.diag-input:hover,
            select.diag-input:focus {
                border-color: #000;
                outline: none;
            }
            select.diag-input::-ms-expand {
                display: none;
            }

            .disabled-note {
                color: #ccc;
                opacity: 0.5;
                font-size: 0.9em;
            }

            .lsg-diag-reset {
                display: inline-block;
                margin-top: 4px;
                background: none;
                border: 1px solid #000;
                color: #000;
                border-radius: 4px;
                padding: 8px 22px;
                cursor: pointer;
                font-size: 0.8em;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                transition: background-color 0.2s ease, color 0.2s ease;
            }
            .lsg-diag-reset:hover {
                background-color: #000;
                color: #fff;
            }

            .lsg-diag-loading {
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px 0 0;
            }
            .lsg-diag-loading.is-active {
                display: flex;
            }
            .lsg-diag-spinner {
                width: 28px;
                height: 28px;
                border: 3px solid rgba(0, 0, 0, 0.15);
                border-top-color: currentColor;
                border-radius: 50%;
                display: inline-block;
                animation: lsg-diag-spin 0.8s linear infinite;
            }
            @keyframes lsg-diag-spin {
                to { transform: rotate(360deg); }
            }

            @media (max-width: 600px) {
                .lsg-diag-card {
                    padding: 28px 20px;
                }
                .lsg-diag-fields {
                    flex-direction: column;
                    align-items: stretch;
                }
                .lsg-diag-field-group {
                    width: 100%;
                }
            }
        </style>
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
 * 3bis. Page de diagnostic complète (shortcode [lsg_diag_parfum]) :
 *    mise en page à 2 colonnes.
 *    - Colonne gauche : H1, H2, les 2 filtres, bouton reset, compteur.
 *    - Colonne droite : loader puis grille de produits filtrés.
 *    La grille de produits elle-même (.lsg-diag-results et son style)
 *    reste identique à ce qu'elle était avant, pour ne pas s'écarter de
 *    la DA des autres pages du site.
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_render_diagnostic_page' ) ) {
    function lsg_diag_render_diagnostic_page() {

        $selected_diag_parfum = isset( $_GET['diag_parfum'] ) ? sanitize_text_field( $_GET['diag_parfum'] ) : lsg_diag_get_default_parfum();
        $selected_diag_note   = isset( $_GET['diag_note'] ) ? sanitize_text_field( $_GET['diag_note'] ) : '';
        $has_active_filter    = isset( $_GET['diag_parfum'] ) || isset( $_GET['diag_note'] );

        $parfum_terms = lsg_diag_get_terms( 'pa_mini_diag_parfum' );
        $note_terms   = lsg_diag_get_terms( 'pa_mini_diag_note' );

        // --- Requête produits (identique à l'ancienne lsg_diag_render_results) ---
        $items_html    = '';
        $visible_count = 0;

        if ( $selected_diag_parfum ) {
            $tax_query = array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'pa_mini_diag_parfum',
                    'field'    => 'slug',
                    'terms'    => $selected_diag_parfum,
                ),
                array(
                    'taxonomy' => 'pa_mini_diag_note',
                    'field'    => 'slug',
                    'terms'    => array( '' ),
                    'operator' => 'NOT IN',
                ),
            );

            if ( $selected_diag_note ) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_mini_diag_note',
                    'field'    => 'slug',
                    'terms'    => $selected_diag_note,
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
        }

        ob_start();
        ?>
        <div class="lsg-diag-layout">

            <!-- ===================== COLONNE GAUCHE ===================== -->
            <div class="lsg-diag-col-left">
                <div class="lsg-diag-card gotu-regular">
                    <h1 class="gotu-regular h3-like lsg-diag-title"><?php echo esc_html__( 'Find your perfume desire', 'lsg_toolbox' ); ?></h1>
                    <h2 class="inter-regular lsg-diag-subtitle"><?php echo esc_html( lsg_t( LSG_DIAG_SUBTITLE_FR, LSG_DIAG_SUBTITLE_EN ) ); ?></h2>

                    <div class="lsg-diag-fields">
                        <!-- Sélection du parfum -->
                        <div class="lsg-diag-field-group">
                            <label class="gotu-regular blocks-caps lsg-diag-label" for="diag_parfum"><?php echo esc_html__( 'I want a trail of perfume', 'lsg_toolbox' ); ?></label>
                            <select class="gotu-regular diag-input" id="diag_parfum" name="diag_parfum" aria-label="<?php echo esc_attr( lsg_t( 'Choisir une famille de parfum', 'Choose a perfume family' ) ); ?>">
                                <?php if ( ! empty( $parfum_terms ) && ! is_wp_error( $parfum_terms ) ) : ?>
                                    <?php foreach ( $parfum_terms as $term ) : ?>
                                        <?php if ( is_object( $term ) ) : ?>
                                            <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_diag_parfum, $term->slug ); ?>>
                                                <?php echo esc_html( $term->name ); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Sélection des notes -->
                        <div class="lsg-diag-field-group" style="position: relative;">
                            <label class="gotu-regular blocks-caps lsg-diag-label" for="diag_note"><?php echo esc_html__( 'with a concord of', 'lsg_toolbox' ); ?></label>
                            <select class="gotu-regular diag-input" id="diag_note" name="diag_note" aria-label="<?php echo esc_attr( lsg_t( 'Choisir une note olfactive', 'Choose a scent note' ) ); ?>">
                                <option value="" selected>...</option>
                                <?php if ( ! empty( $note_terms ) && ! is_wp_error( $note_terms ) ) : ?>
                                    <?php foreach ( $note_terms as $term ) : ?>
                                        <?php if ( is_object( $term ) ) : ?>
                                            <?php
                                            $is_disabled = ! lsg_diag_term_associated_with_parfum( $term->slug, $selected_diag_parfum );
                                            $class       = $is_disabled ? 'class="disabled-note"' : '';
                                            $disabled    = $is_disabled ? 'disabled' : '';
                                            ?>
                                            <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_diag_note, $term->slug ); ?> <?php echo $class; ?> <?php echo $disabled; ?>>
                                                <?php echo esc_html( $term->name ); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="hint-box top" id="hint-diag" style="top: 35px; left: -82px; display:none;">
                                <?php echo esc_html__( 'Fill those fields to find your perfume!', 'lsg_toolbox' ); ?>
                            </div>
                        </div>
                    </div>

                    <?php if ( $has_active_filter ) : ?>
                        <button type="button" id="lsg-diag-reset" class="lsg-diag-reset">
                            <?php echo esc_html( lsg_t( 'Réinitialiser', 'Reset' ) ); ?>
                        </button>
                    <?php endif; ?>

                    <p class="gotu-regular blocks-caps lsg-diag-count">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of matching perfumes */
                                _n( '%d parfum trouvé', '%d parfums trouvés', $visible_count, 'lsg_toolbox' ),
                                $visible_count
                            )
                        );
                        ?>
                    </p>
                </div>
            </div>

            <!-- ===================== COLONNE DROITE ===================== -->
            <div class="lsg-diag-col-right">
                <div id="lsg-diag-loading" class="lsg-diag-loading" aria-hidden="true">
                    <span class="lsg-diag-spinner"></span>
                </div>

                <div class="lsg-diag-results-wrapper" aria-live="polite">
                    <?php if ( $visible_count > 0 ) : ?>
                        <ul class="lsg-diag-results">
                            <?php echo $items_html; ?>
                        </ul>
                    <?php else : ?>
                        <p class="lsg-diag-no-result"><?php echo esc_html( lsg_t( 'Aucun parfum ne correspond encore à cette combinaison.', 'No perfume matches this combination yet.' ) ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const parfumSelect   = document.getElementById('diag_parfum');
                const noteSelect     = document.getElementById('diag_note');
                const resetBtn       = document.getElementById('lsg-diag-reset');
                const loadingOverlay = document.getElementById('lsg-diag-loading');

                if (!parfumSelect || !noteSelect) return;

                function showLoading() {
                    if (loadingOverlay) {
                        loadingOverlay.classList.add('is-active');
                        loadingOverlay.setAttribute('aria-hidden', 'false');
                    }
                }

                function updateURL() {
                    const selectedParfum = parfumSelect.value;
                    const selectedNote   = noteSelect.value;

                    let url = new URL(window.location.href);

                    if (selectedParfum) {
                        url.searchParams.set('diag_parfum', selectedParfum);
                    } else {
                        url.searchParams.delete('diag_parfum');
                    }

                    if (selectedNote) {
                        url.searchParams.set('diag_note', selectedNote);
                    } else {
                        url.searchParams.delete('diag_note');
                    }

                    url.searchParams.set('diag', '1');

                    showLoading();
                    window.location.href = url.toString();
                }

                parfumSelect.addEventListener('change', function() {
                    noteSelect.selectedIndex = 0;
                    updateURL();
                });

                noteSelect.addEventListener('change', updateURL);

                if (resetBtn) {
                    resetBtn.addEventListener('click', function() {
                        showLoading();
                        window.location.href = window.location.origin + window.location.pathname;
                    });
                }
            });
        </script>

        <style>
            /* ---------- Mise en page 2 colonnes, mobile-first ---------- */
            .lsg-diag-layout {
                display: grid;
                grid-template-columns: 1fr;
                gap: 32px;
                max-width: 1280px;
                margin: 0 auto;
                padding: 0 16px;
                align-items: start;
            }

            /* Tablette */
            @media (min-width: 768px) {
                .lsg-diag-layout {
                    grid-template-columns: 280px 1fr;
                    gap: 32px;
                }
            }

            /* Laptop */
            @media (min-width: 1024px) {
                .lsg-diag-layout {
                    grid-template-columns: 320px 1fr;
                    gap: 40px;
                }
            }

            /* Grand écran / desktop large */
            @media (min-width: 1440px) {
                .lsg-diag-layout {
                    grid-template-columns: 360px 1fr;
                    gap: 56px;
                }
            }

            /* ---------- Colonne gauche : carte titre + filtres ---------- */
            .lsg-diag-card {
                background-color: #F9F9F9;
                border-radius: 8px;
                padding: 40px 32px;
                text-align: center;
            }

            .lsg-diag-title {
                margin-bottom: 12px;
            }

            .lsg-diag-subtitle {
                font-size: 0.95em;
                font-weight: 400;
                opacity: 0.75;
                margin: 0 0 28px;
            }

            .lsg-diag-fields {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 24px;
                margin-bottom: 24px;
            }

            .lsg-diag-field-group {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
                width: 100%;
            }

            .lsg-diag-label {
                font-size: 0.75em;
                letter-spacing: 0.05em;
                opacity: 0.7;
                margin-bottom: 8px;
            }

            select.diag-input {
                width: 100%;
                padding: 12px 40px 12px 14px;
                border: none;
                border-bottom: 1px solid #000;
                border-radius: 0;
                background-color: #fff;
                font-size: 15px;
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                cursor: pointer;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23000' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 14px center;
                background-size: 12px 8px;
                transition: border-color 0.2s ease;
            }
            select.diag-input:hover,
            select.diag-input:focus {
                border-color: #000;
                outline: none;
            }
            select.diag-input::-ms-expand {
                display: none;
            }

            .disabled-note {
                color: #ccc;
                opacity: 0.5;
                font-size: 0.9em;
            }

            .lsg-diag-reset {
                display: inline-block;
                margin-top: 4px;
                background: none;
                border: 1px solid #000;
                color: #000;
                border-radius: 4px;
                padding: 8px 22px;
                cursor: pointer;
                font-size: 0.8em;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                transition: background-color 0.2s ease, color 0.2s ease;
            }
            .lsg-diag-reset:hover {
                background-color: #000;
                color: #fff;
            }

            .lsg-diag-count {
                font-size: 0.8em;
                letter-spacing: 0.05em;
                opacity: 0.7;
                margin: 24px 0 0;
                padding-top: 20px;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
            }

            /* ---------- Colonne droite : loader + grille (inchangée) ---------- */
            .lsg-diag-loading {
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px 0;
            }
            .lsg-diag-loading.is-active {
                display: flex;
            }
            .lsg-diag-spinner {
                width: 28px;
                height: 28px;
                border: 3px solid rgba(0, 0, 0, 0.15);
                border-top-color: currentColor;
                border-radius: 50%;
                display: inline-block;
                animation: lsg-diag-spin 0.8s linear infinite;
            }
            @keyframes lsg-diag-spin {
                to { transform: rotate(360deg); }
            }

            .lsg-diag-results {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 24px;
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .lsg-diag-result-item a {
                display: block;
                text-decoration: none;
                color: inherit;
                text-align: center;
            }
            .lsg-diag-result-item img {
                max-width: 100%;
                height: auto;
            }
            .lsg-diag-result-item h3 {
                font-size: 16px;
                margin: 10px 0 4px;
            }
            .lsg-diag-no-result {
                text-align: center;
                padding: 40px 0;
                opacity: 0.7;
            }
        </style>
        <?php

        return ob_get_clean();
    }
}

/**
 * =========================================================================
 * 3ter. SEO : titre d'onglet dynamique et balise canonical.
 *    Comme le résultat dépend de paramètres GET, on adapte le <title>
 *    à la sélection en cours et on pointe le canonical vers l'URL propre
 *    de la page (sans paramètres) pour éviter le duplicate content.
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

/**
 * =========================================================================
 * 6. Petit tooltip d'aide ("Fill those fields to find your perfume!")
 *    qui s'affiche tant que le cookie "hintdiag" n'est pas posé.
 *    (Anciennement noyé dans menu_script_depliants() du functions.php)
 * =========================================================================
 */
if ( ! function_exists( 'lsg_diag_hint_script' ) ) {
    function lsg_diag_hint_script() {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {

                var hintdiag   = document.getElementById('hint-diag');
                var diag_note  = document.getElementById('diag_note');
                var diag_parfum = document.getElementById('diag_parfum');
                var phpcookie = "<?php echo isset( $_COOKIE['hintdiag'] ) ? esc_js( $_COOKIE['hintdiag'] ) : ''; ?>";

                if (hintdiag) {
                    if (phpcookie === "") {
                        hintdiag.style.display = "block";
                    }

                    hintdiag.addEventListener('click', function() {
                        hintdiag.style.display = "none";
                        document.cookie = "hintdiag=done; path=/";
                    });
                }

                if (diag_note && diag_parfum) {
                    diag_note.addEventListener('click', function() {
                        hintdiag.style.display = "none";
                        document.cookie = "hintdiag=done; path=/";
                    });
                    diag_parfum.addEventListener('click', function() {
                        hintdiag.style.display = "none";
                        document.cookie = "hintdiag=done; path=/";
                    });
                }
            });
        </script>
        <?php
    }
}
add_action( 'wp_footer', 'lsg_diag_hint_script', 1 );
