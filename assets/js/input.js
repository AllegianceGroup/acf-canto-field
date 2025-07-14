(function($, undefined) {
    
    /**
     * Initialize ACF Canto Field
     */
    function initialize_field($field) {
        
        var $container = $field.find('.acf-canto-container');
        var $modal = $field.find('.acf-canto-modal');
        var $searchInput = $modal.find('.acf-canto-search-input');
        var $searchBtn = $modal.find('.acf-canto-search-btn');
        var $results = $modal.find('.acf-canto-assets-grid');
        var $loading = $modal.find('.acf-canto-loading');
        var $confirmBtn = $modal.find('.acf-canto-confirm-selection');
        var $cancelBtn = $modal.find('.acf-canto-cancel');
        var $closeBtn = $modal.find('.acf-canto-modal-close');
        var $hiddenInput = $field.find('input[type="hidden"]');
        
        // Tree navigation elements
        var $navTabs = $modal.find('.acf-canto-nav-tab');
        var $searchView = $modal.find('.acf-canto-search-view');
        var $browseView = $modal.find('.acf-canto-browse-view');
        var $treeContainer = $modal.find('.acf-canto-tree-list');
        var $treeLoading = $modal.find('.acf-canto-tree-loading');
        var $browseAssets = $modal.find('.acf-canto-browse-assets');
        var $browseLoading = $modal.find('.acf-canto-browse-loading');
        var $currentPath = $modal.find('.acf-canto-current-path');
        var $treeRefresh = $modal.find('.acf-canto-tree-refresh');
        var $browseRefresh = $modal.find('.acf-canto-browse-refresh');
        
        var selectedAsset = null;
        var currentAlbumId = null;
        
        // Add error handling for existing preview images
        $field.find('.acf-canto-preview-image img').on('error', function() {
            console.log('ACF Canto: Preview image failed to load:', $(this).attr('src'));
            
            // Try to determine asset type from URL or use default
            var src = $(this).attr('src') || '';
            var scheme = 'image'; // default
            
            if (src.indexOf('/video/') !== -1) {
                scheme = 'video';
            } else if (src.indexOf('/document/') !== -1) {
                scheme = 'document';
            }
            
            var defaultThumb = getDefaultThumbnail(scheme);
            $(this).attr('src', defaultThumb);
        });
        
        // Open modal
        $field.on('click', '.acf-canto-select, .acf-canto-edit', function(e) {
            e.preventDefault();
            openModal();
        });
        
        // Remove asset
        $field.on('click', '.acf-canto-remove', function(e) {
            e.preventDefault();
            removeAsset();
        });
        
        // Close modal
        $closeBtn.add($cancelBtn).on('click', function(e) {
            e.preventDefault();
            closeModal();
        });
        
        // Close modal on background click
        $modal.on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Search functionality
        $searchBtn.on('click', function(e) {
            e.preventDefault();
            performSearch();
        });
        
        $searchInput.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                performSearch();
            }
        });
        
        // Tab navigation
        $navTabs.on('click', function(e) {
            e.preventDefault();
            var view = $(this).data('view');
            switchView(view);
        });
        
        // Tree navigation
        $treeRefresh.on('click', function(e) {
            e.preventDefault();
            loadTree();
        });
        
        $browseRefresh.on('click', function(e) {
            e.preventDefault();
            if (currentAlbumId) {
                loadAlbumAssets(currentAlbumId);
            } else {
                performSearch('', $browseAssets, $browseLoading);
            }
        });
        
        // Tree item clicks (delegated)
        $treeContainer.on('click', '.acf-canto-tree-expand', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $item = $(this).closest('.acf-canto-tree-item');
            toggleTreeItem($item);
        });
        
        $treeContainer.on('click', '.acf-canto-tree-link', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.acf-canto-tree-item');
            var albumId = $item.data('album-id');
            var albumName = $(this).find('.acf-canto-tree-name').text();
            var scheme = $item.data('scheme');
            
            // Only load assets for albums, not folders
            if (scheme === 'album') {
                selectAlbum(albumId, albumName);
            } else {
                // For folders, just expand/collapse them
                toggleTreeItem($item);
            }
        });
        
        // Asset selection (delegated to modal for both views)
        $modal.on('click', '.acf-canto-asset-item', function(e) {
            e.preventDefault();
            selectAsset($(this));
        });
        
        // Confirm selection
        $confirmBtn.on('click', function(e) {
            e.preventDefault();
            confirmSelection();
        });
        
        /**
         * Open the modal and load initial assets
         */
        function openModal() {
            $modal.show();
            $('body').addClass('acf-canto-modal-open');
            
            // Load initial assets if results are empty
            if ($results.children().length === 0) {
                performSearch('');
            }
        }
        
        /**
         * Close the modal
         */
        function closeModal() {
            $modal.hide();
            $('body').removeClass('acf-canto-modal-open');
            selectedAsset = null;
            $confirmBtn.prop('disabled', true);
            $results.find('.acf-canto-asset-item').removeClass('selected');
        }
        
        /**
         * Remove the current asset
         */
        function removeAsset() {
            $hiddenInput.val('');
            $container.html('<div class="acf-canto-placeholder"><button type="button" class="button button-primary acf-canto-select">' + acf_canto.l10n.select + '</button></div>');
            $field.trigger('change');
        }
        
        /**
         * Perform search for assets
         */
        function performSearch(query, targetContainer, loadingElement) {
            if (typeof query === 'undefined') {
                query = $searchInput.val();
            }
            if (typeof targetContainer === 'undefined') {
                targetContainer = $results;
            }
            if (typeof loadingElement === 'undefined') {
                loadingElement = $loading;
            }
            
            console.log('ACF Canto: Performing search with query:', query);
            
            loadingElement.show();
            targetContainer.empty();
            
            var data = {
                action: 'acf_canto_search',
                nonce: acf_canto.nonce,
                query: query,
                selected_id: $hiddenInput.val()
            };
            
            console.log('ACF Canto: AJAX data:', data);
            
            $.post(acf_canto.ajax_url, data)
                .done(function(response) {
                    console.log('ACF Canto: AJAX response:', response);
                    loadingElement.hide();
                    
                    if (response.success && response.data) {
                        displayAssets(response.data, targetContainer);
                    } else {
                        var errorMsg = response.data || acf_canto.l10n.no_assets;
                        console.error('ACF Canto: Error:', errorMsg);
                        targetContainer.html('<div class="acf-canto-no-results">' + errorMsg + '</div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('ACF Canto: AJAX failed:', status, error);
                    loadingElement.hide();
                    targetContainer.html('<div class="acf-canto-error">Error loading assets: ' + error + '. Please try again.</div>');
                });
        }
        
        /**
         * Display assets in the grid
         */
        function displayAssets(assets, targetContainer) {
            if (typeof targetContainer === 'undefined') {
                targetContainer = $results;
            }
            
            targetContainer.empty();
            
            if (!assets || assets.length === 0) {
                targetContainer.html('<div class="acf-canto-no-results">' + acf_canto.l10n.no_assets + '</div>');
                return;
            }
            
            // Create grid container if it doesn't exist
            var $grid = targetContainer.hasClass('acf-canto-assets-grid') ? 
                        targetContainer : 
                        targetContainer.find('.acf-canto-assets-grid');
            
            if ($grid.length === 0) {
                $grid = $('<div class="acf-canto-assets-grid">');
                targetContainer.append($grid);
            } else {
                $grid.empty();
            }
            
            $.each(assets, function(index, asset) {
                var $item = $('<div class="acf-canto-asset-item" data-asset-id="' + asset.id + '">');
                
                // Thumbnail
                if (asset.thumbnail) {
                    var $thumb = $('<div class="acf-canto-asset-thumb">');
                    var $img = $('<img>').attr({
                        'src': asset.thumbnail,
                        'alt': asset.name
                    });
                    
                    // Handle broken images with fallback
                    $img.on('error', function() {
                        console.log('ACF Canto: Image failed to load:', asset.thumbnail);
                        var defaultThumb = getDefaultThumbnail(asset.scheme);
                        $(this).attr('src', defaultThumb);
                    });
                    
                    $thumb.append($img);
                    $item.append($thumb);
                } else {
                    $item.append('<div class="acf-canto-asset-thumb acf-canto-no-thumb"><span>' + asset.scheme + '</span></div>');
                }
                
                // Details
                var $details = $('<div class="acf-canto-asset-details">');
                $details.append('<h4>' + asset.name + '</h4>');
                
                if (asset.dimensions) {
                    $details.append('<p>' + asset.dimensions + '</p>');
                }
                if (asset.size) {
                    $details.append('<p>' + asset.size + '</p>');
                }
                
                $item.append($details);
                
                // Mark as selected if it's the current value
                if (asset.id === $hiddenInput.val()) {
                    $item.addClass('selected');
                    selectedAsset = asset;
                    $confirmBtn.prop('disabled', false);
                }
                
                $grid.append($item);
            });
        }
        
        /**
         * Select an asset
         */
        function selectAsset($item) {
            var assetId = $item.data('asset-id');
            
            // Remove previous selection from all views
            $modal.find('.acf-canto-asset-item').removeClass('selected');
            
            // Mark as selected
            $item.addClass('selected');
            
            // Find asset data
            selectedAsset = null;
            $modal.find('.acf-canto-asset-item').each(function() {
                if ($(this).data('asset-id') === assetId) {
                    selectedAsset = {
                        id: assetId,
                        name: $(this).find('h4').text(),
                        thumbnail: $(this).find('img').attr('src') || '',
                        dimensions: $(this).find('p').eq(0).text() || '',
                        size: $(this).find('p').eq(1).text() || ''
                    };
                    return false;
                }
            });
            
            $confirmBtn.prop('disabled', false);
        }
        
        /**
         * Confirm the selection and update the field
         */
        function confirmSelection() {
            if (!selectedAsset) {
                return;
            }
            
            // Update hidden input
            $hiddenInput.val(selectedAsset.id);
            
            // Update preview
            updatePreview(selectedAsset);
            
            // Close modal
            closeModal();
            
            // Trigger change event
            $field.trigger('change');
        }
        
        /**
         * Update the field preview
         */
        function updatePreview(asset) {
            var html = '<div class="acf-canto-preview">';
            html += '<div class="acf-canto-preview-image">';
            
            if (asset.thumbnail) {
                html += '<img src="' + asset.thumbnail + '" alt="' + asset.name + '" />';
            }
            
            html += '</div>';
            html += '<div class="acf-canto-preview-details">';
            html += '<h4>' + asset.name + '</h4>';
            
            if (asset.dimensions) {
                html += '<p>' + asset.dimensions + '</p>';
            }
            if (asset.size) {
                html += '<p>' + asset.size + '</p>';
            }
            
            html += '</div>';
            html += '<div class="acf-canto-actions">';
            html += '<button type="button" class="button acf-canto-edit">' + acf_canto.l10n.edit + '</button>';
            html += '<button type="button" class="button acf-canto-remove">' + acf_canto.l10n.remove + '</button>';
            html += '</div>';
            html += '</div>';
            
            $container.html(html);
            
            // Add error handling to preview image
            $container.find('img').on('error', function() {
                console.log('ACF Canto: Preview image failed to load:', $(this).attr('src'));
                var defaultThumb = getDefaultThumbnail(asset.scheme);
                $(this).attr('src', defaultThumb);
            });
        }
        
        /**
         * Get default thumbnail URL based on asset type
         */
        function getDefaultThumbnail(scheme) {
            var baseUrl = acf_canto.plugin_url;
            switch (scheme) {
                case 'video':
                    return baseUrl + 'assets/images/default-video.svg';
                case 'document':
                    return baseUrl + 'assets/images/default-document.svg';
                case 'image':
                default:
                    return baseUrl + 'assets/images/default-image.svg';
            }
        }
        
        /**
         * Switch between search and browse views
         */
        function switchView(view) {
            $navTabs.removeClass('active');
            $navTabs.filter('[data-view="' + view + '"]').addClass('active');
            
            $('.acf-canto-view').removeClass('active');
            
            if (view === 'search') {
                $searchView.addClass('active');
            } else if (view === 'browse') {
                $browseView.addClass('active');
                // Load tree if not already loaded
                if ($treeContainer.is(':empty')) {
                    loadTree();
                }
                // Load recent assets by default
                if ($browseAssets.is(':empty')) {
                    performSearch('', $browseAssets, $browseLoading);
                }
            }
        }
        
        /**
         * Load the folder/album tree
         */
        function loadTree(albumId) {
            console.log('ACF Canto: Loading tree for album:', albumId);
            
            $treeLoading.show();
            
            var data = {
                action: 'acf_canto_get_tree',
                nonce: acf_canto.nonce,
                album_id: albumId || ''
            };
            
            $.post(acf_canto.ajax_url, data)
                .done(function(response) {
                    $treeLoading.hide();
                    
                    if (response.success && response.data) {
                        if (albumId) {
                            // This is for expanding a specific album
                            var $item = $treeContainer.find('[data-album-id="' + albumId + '"]');
                            displayTreeChildren($item, response.data);
                        } else {
                            // This is the root tree
                            displayTree(response.data);
                        }
                    } else {
                        console.error('ACF Canto: Tree loading error:', response.data);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('ACF Canto: Tree AJAX failed:', status, error);
                    $treeLoading.hide();
                });
        }
        
        /**
         * Display the tree structure
         */
        function displayTree(data) {
            $treeContainer.empty();
            
            if (!data || !data.results || data.results.length === 0) {
                $treeContainer.html('<li class="acf-canto-tree-item"><div class="acf-canto-tree-link">No albums found</div></li>');
                return;
            }
            
            $.each(data.results, function(index, item) {
                var $item = createTreeItem(item);
                $treeContainer.append($item);
            });
        }
        
        /**
         * Create a tree item element
         */
        function createTreeItem(item) {
            // Check if this item has children or could have children
            var hasChildren = item.children && item.children.length > 0;
            var isAlbum = item.scheme === 'album';
            var couldHaveChildren = !isAlbum && (item.type === 'folder' || hasChildren);
            
            // Determine icon based on type
            var iconClass = isAlbum ? '📁' : '📂'; // Different icons for albums vs folders
            
            var $item = $('<li class="acf-canto-tree-item" data-album-id="' + item.id + '" data-scheme="' + (item.scheme || 'folder') + '">');
            
            var $link = $('<div class="acf-canto-tree-link">');
            
            if (couldHaveChildren) {
                $link.append('<span class="acf-canto-tree-expand">+</span>');
            } else {
                $link.append('<span class="acf-canto-tree-expand"></span>');
            }
            
            $link.append('<span class="acf-canto-tree-icon">' + iconClass + '</span>');
            $link.append('<span class="acf-canto-tree-name">' + (item.name || 'Untitled') + '</span>');
            
            $item.append($link);
            
            // Only pre-populate immediate children if they exist
            if (hasChildren) {
                var $children = $('<ul class="acf-canto-tree-children">');
                $.each(item.children, function(index, child) {
                    $children.append(createTreeItem(child));
                });
                $item.append($children);
                $item.addClass('expanded');
                $link.find('.acf-canto-tree-expand').text('-');
            }
            
            return $item;
        }
        
        /**
         * Display tree children for expanded item
         */
        function displayTreeChildren($item, data) {
            var $children = $item.find('> .acf-canto-tree-children');
            if ($children.length === 0) {
                $children = $('<ul class="acf-canto-tree-children">');
                $item.append($children);
            }
            
            $children.empty();
            
            if (data && data.results) {
                $.each(data.results, function(index, child) {
                    $children.append(createTreeItem(child));
                });
            }
        }
        
        /**
         * Toggle tree item expansion
         */
        function toggleTreeItem($item) {
            if ($item.hasClass('expanded')) {
                $item.removeClass('expanded');
                $item.find('> .acf-canto-tree-link .acf-canto-tree-expand').text('+');
            } else {
                $item.addClass('expanded');
                $item.find('> .acf-canto-tree-link .acf-canto-tree-expand').text('-');
                
                // Load children if not already loaded
                var $children = $item.find('> .acf-canto-tree-children');
                if ($children.length === 0 || $children.is(':empty')) {
                    var albumId = $item.data('album-id');
                    loadSubfolders(albumId, $item);
                }
            }
        }
        
        /**
         * Load subfolders for a specific item
         */
        function loadSubfolders(albumId, $parentItem) {
            console.log('ACF Canto: Loading subfolders for:', albumId);
            
            var data = {
                action: 'acf_canto_get_tree',
                nonce: acf_canto.nonce,
                album_id: albumId
            };
            
            $.post(acf_canto.ajax_url, data)
                .done(function(response) {
                    if (response.success && response.data) {
                        displayTreeChildren($parentItem, response.data);
                    } else {
                        console.error('ACF Canto: Subfolder loading error:', response.data);
                        // Show that this folder has no subfolders
                        $parentItem.find('> .acf-canto-tree-link .acf-canto-tree-expand').text('');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('ACF Canto: Subfolder AJAX failed:', status, error);
                    $parentItem.find('> .acf-canto-tree-link .acf-canto-tree-expand').text('');
                });
        }
        
        /**
         * Select an album and load its assets
         */
        function selectAlbum(albumId, albumName) {
            // Update active state
            $treeContainer.find('.acf-canto-tree-link').removeClass('active');
            $treeContainer.find('[data-album-id="' + albumId + '"] > .acf-canto-tree-link').addClass('active');
            
            // Update current path
            $currentPath.text(albumName || 'Selected Album');
            
            // Load album assets
            currentAlbumId = albumId;
            loadAlbumAssets(albumId);
        }
        
        /**
         * Load assets from a specific album
         */
        function loadAlbumAssets(albumId) {
            console.log('ACF Canto: Loading assets for album:', albumId);
            
            $browseLoading.show();
            $browseAssets.empty();
            
            var data = {
                action: 'acf_canto_get_album',
                nonce: acf_canto.nonce,
                album_id: albumId
            };
            
            $.post(acf_canto.ajax_url, data)
                .done(function(response) {
                    $browseLoading.hide();
                    
                    if (response.success && response.data) {
                        displayAssets(response.data, $browseAssets);
                    } else {
                        var errorMsg = response.data || 'No assets found in this album';
                        $browseAssets.html('<div class="acf-canto-no-results">' + errorMsg + '</div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('ACF Canto: Album assets AJAX failed:', status, error);
                    $browseLoading.hide();
                    $browseAssets.html('<div class="acf-canto-error">Error loading album assets: ' + error + '</div>');
                });
        }
    }
    
    /**
     * ACF Integration
     */
    if (typeof acf !== 'undefined') {
        
        acf.add_action('ready_field/type=canto', initialize_field);
        acf.add_action('append_field/type=canto', initialize_field);
        
    }
    
})(jQuery);
