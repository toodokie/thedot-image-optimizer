<?php
/**
 * MSH WebP Delivery System
 * Automatically serves WebP images to compatible browsers
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_WebP_Delivery {
    
    private $webp_support = null;
    
    public function __construct() {
        // Hook into WordPress image output
        add_filter('wp_get_attachment_image_src', array($this, 'maybe_serve_webp'), 10, 4);
        add_filter('wp_get_attachment_url', array($this, 'maybe_serve_webp_url'), 10, 2);
        add_filter('the_content', array($this, 'replace_images_in_content'), 99);
        add_filter('post_thumbnail_html', array($this, 'replace_images_in_html'), 99);
        add_filter('get_header_image', array($this, 'maybe_serve_webp_header'));

        // Add WebP support detection script
        add_action('wp_head', array($this, 'add_webp_detection_script'), 1);

        // Handle AJAX detection fallback
        add_action('wp_ajax_msh_detect_webp', array($this, 'ajax_detect_webp'));
        add_action('wp_ajax_nopriv_msh_detect_webp', array($this, 'ajax_detect_webp'));
    }
    
    /**
     * Detect WebP support in browser
     */
    private function browser_supports_webp() {
        if ($this->webp_support !== null) {
            return $this->webp_support;
        }

        // Check if already detected via cookie
        if (isset($_COOKIE['webp_support'])) {
            $this->webp_support = ($_COOKIE['webp_support'] === '1');
            return $this->webp_support;
        }

        // Check Accept header (fallback)
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
            $this->webp_support = true;
            return $this->webp_support;
        }

        // Check User-Agent for known WebP support
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            // Chrome, Edge, Firefox, Opera support WebP
            if (preg_match('/(Chrome|Chromium|Edge|Firefox|Opera)/', $user_agent)) {
                $this->webp_support = true;
                return $this->webp_support;
            }
        }

        // Default to false for safety
        $this->webp_support = false;
        return $this->webp_support;
    }
    
    /**
     * Check if WebP version exists for a given image path
     */
    private function webp_exists($image_path) {
        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_path);
        
        // Handle both full paths and URLs
        if (strpos($image_path, 'http') === 0) {
            // Convert URL to file path
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_path);
            return file_exists($file_path);
        } else {
            // Direct file path
            return file_exists($webp_path);
        }
    }
    
    /**
     * Convert image path to WebP if supported and available
     */
    private function get_webp_path($image_path) {
        if (!$this->browser_supports_webp()) {
            return $image_path;
        }

        // Skip SVG files
        if (preg_match('/\.svg$/i', $image_path)) {
            return $image_path;
        }

        // Only convert jpg, jpeg, png
        if (!preg_match('/\.(jpg|jpeg|png)$/i', $image_path)) {
            return $image_path;
        }

        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_path);

        // Check if WebP version exists
        if ($this->webp_exists($image_path)) {
            return $webp_path;
        }

        return $image_path; // Fallback to original
    }
    
    /**
     * Filter: wp_get_attachment_image_src
     */
    public function maybe_serve_webp($image, $attachment_id, $size, $icon) {
        if (!$image || !is_array($image)) {
            return $image;
        }

        $image[0] = $this->get_webp_path($image[0]);
        return $image;
    }
    
    /**
     * Filter: wp_get_attachment_url
     */
    public function maybe_serve_webp_url($url, $attachment_id) {
        return $this->get_webp_path($url);
    }
    
    /**
     * Filter: the_content - Replace images in post content
     */
    public function replace_images_in_content($content) {
        if (!$this->browser_supports_webp()) {
            return $content;
        }
        
        // Replace img src attributes
        $content = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']*?\.(jpg|jpeg|png))["\']([^>]*?)>/i',
            array($this, 'replace_img_callback'),
            $content
        );
        
        return $content;
    }
    
    /**
     * Filter: post_thumbnail_html - Replace images in featured image HTML
     */
    public function replace_images_in_html($html) {
        if (!$this->browser_supports_webp()) {
            return $html;
        }
        
        // Replace img src attributes
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']*?\.(jpg|jpeg|png))["\']([^>]*?)>/i',
            array($this, 'replace_img_callback'),
            $html
        );
        
        return $html;
    }
    
    /**
     * Callback for image replacement
     */
    private function replace_img_callback($matches) {
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[4];
        
        $webp_src = $this->get_webp_path($src);
        
        // If WebP version exists, create picture element for better fallback
        if ($webp_src !== $src && $this->webp_exists($src)) {
            return sprintf(
                '<picture><source srcset="%s" type="image/webp"><img%ssrc="%s"%s></picture>',
                esc_attr($webp_src),
                $before_src,
                esc_attr($src),
                $after_src
            );
        }
        
        // No WebP available, return original
        return $matches[0];
    }
    
    /**
     * Filter: get_header_image - Handle header images
     */
    public function maybe_serve_webp_header($url) {
        return $this->get_webp_path($url);
    }
    
    /**
     * Add WebP detection script to head
     */
    public function add_webp_detection_script() {
        ?>
        <script>
        // WebP detection and cookie setting
        (function() {
            var webp = new Image();
            webp.onload = webp.onerror = function () {
                var supported = (webp.height == 2);
                document.cookie = 'webp_support=' + (supported ? '1' : '0') + '; path=/; max-age=31536000'; // 1 year
                
                // If this is the first detection, reload to serve correct images
                if (!document.cookie.match(/webp_support=/)) {
                    // Small delay to ensure cookie is set
                    setTimeout(function() {
                        if (supported && !document.body.classList.contains('webp-detected')) {
                            document.body.classList.add('webp-detected');
                            // Optionally reload page for immediate WebP serving
                            // location.reload();
                        }
                    }, 100);
                }
            };
            webp.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX handler for WebP detection (fallback)
     */
    public function ajax_detect_webp() {
        $supported = isset($_POST['webp_supported']) ? (bool) $_POST['webp_supported'] : false;
        
        // Set cookie for future requests
        setcookie('webp_support', $supported ? '1' : '0', time() + YEAR_IN_SECONDS, '/');
        
        wp_send_json_success(['webp_supported' => $supported]);
    }
    
    /**
     * Get WebP support status for debugging
     */
    public function get_webp_status() {
        return [
            'browser_supports' => $this->browser_supports_webp(),
            'cookie_set' => isset($_COOKIE['webp_support']),
            'cookie_value' => $_COOKIE['webp_support'] ?? 'not set',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not available',
            'accept_header' => $_SERVER['HTTP_ACCEPT'] ?? 'not available'
        ];
    }
}

// Initialize WebP delivery
new MSH_WebP_Delivery();