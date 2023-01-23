local M = {
    enabled = true,
    priority = 80,
    servers = {
        intelephense = {
            init_options = {
                licenceKey = os.getenv('HOME') .. '/.intelephense/license.txt'
            },
            settings = {
                 intelephense = {
                    phpdoc = {
                        classTemplate = {
                            summary = [[$SYMBOL_KIND ${SYMBOL_NAME/^(.+)\\([^\\]+)$/$2/}]],
                            tags = {
                                '',
                                '@package $SYMBOL_NAMESPACE'
                            }
                        },
                        functionTemplate = {
                            summary = '$1',
                            tags = {
                                '',
                                '@param ${1:$SYMBOL_TYPE} $SYMBOL_NAME',
                                '@return ${1:$SYMBOL_TYPE}',
                                '@throws ${1:$SYMBOL_TYPE}'
                            }
                        },
                        useFullyQualifiedNames = true
                    }
                }
            }
        },
        sumneko_lua = {
            settings = {
                Lua = {
                    workspace = { checkThirdParty = false },
                    telemetry = { enable = false }
                }
            }
        }
    }
}

function M.install(use)
    use { -- LSP Configuration & Plugins
        'neovim/nvim-lspconfig',
        requires = {
            -- Automatically install LSPs to stdpath for neovim
            'williamboman/mason.nvim',
            'williamboman/mason-lspconfig.nvim',

            -- Useful status updates for LSP
            'j-hui/fidget.nvim',

            -- Additional lua configuration, makes nvim stuff amazing
            'folke/neodev.nvim'
        }
    }

    use { 'glepnir/lspsaga.nvim', branch = 'main' }
end

function M.configure()
    -- Setup neovim lua configuration - MUST be setup before loading LSP (learned this the hard way and lost 4 hours of my life being pissed off)
    -- Also a reminder to RTFM
    require('neodev').setup()

    require('lspsaga').setup({
        symbol_in_winbar = {
            separator = '  '
        }
    })

    -- LSP settings
    local on_attach = function(_, bufnr)
        local nnoremap = require('user.keymap').factory('n', { buffer = bufnr, remap = false })
        local inoremap = require('user.keymap').factory('i', { buffer = bufnr, remap = false })
        local telescope = require('telescope.builtin');

        nnoremap('<leader>cr', '<cmd>Lspsaga rename<cr>', '[C]ode - [R]ename', { silent = true })
        nnoremap('<leader>ca', '<cmd>Lspsaga code_action<cr>', '[C]ode - [A]ction', { silent = true })

        nnoremap('gd', telescope.lsp_definitions, '[G]oto [D]efinition', { silent = true })
        nnoremap('gD', vim.lsp.buf.declaration, '[G]oto [D]eclaration')
        nnoremap('gu', telescope.lsp_references, '[G]oto [U]sages')
        nnoremap('gi', telescope.lsp_implementations, '[G]oto [I]mplementation')
        nnoremap('gt', telescope.lsp_type_definitions, '[G]oto [T]ype Definition')
        nnoremap('<leader>ss', telescope.lsp_document_symbols, '[S]earch Document [S]ymbols')

        -- See `:help K` for why this keymap
        nnoremap('K', '<cmd>Lspsaga hover_doc<cr>', 'Hover Documentation')
        inoremap('<C-p>', vim.lsp.buf.signature_help, '[P]aram/Signature Documentation')

        nnoremap('<leader>ws', telescope.lsp_dynamic_workspace_symbols, '[W]orkspace [S]ymbols')
        nnoremap('<leader>wa', vim.lsp.buf.add_workspace_folder, '[W]orkspace [A]dd Folder')
        nnoremap('<leader>wr', vim.lsp.buf.remove_workspace_folder, '[W]orkspace [R]emove Folder')
        nnoremap('<leader>wl', function()
            print(vim.inspect(vim.lsp.buf.list_workspace_folders()))
        end, '[W]orkspace [L]ist Folders')

        -- Create a command `:Format` local to the LSP buffer
        vim.api.nvim_buf_create_user_command(bufnr, 'Format', function(_)
            vim.lsp.buf.format()
        end, { desc = 'Format current buffer with LSP' })
    end

    -- nvim-cmp supports additional completion capabilities, so broadcast that to servers
    local capabilities = vim.lsp.protocol.make_client_capabilities()
    capabilities = require('cmp_nvim_lsp').default_capabilities(capabilities)

    -- Setup mason so it can manage external tooling
    require('mason').setup()

    -- Ensure the servers above are installed
    local mason_lspconfig = require 'mason-lspconfig'

    mason_lspconfig.setup {
        ensure_installed = vim.tbl_keys(M.servers),
    }

    mason_lspconfig.setup_handlers {
        function(server_name)
            local config = M.servers[server_name] or {}
            config.capabilities = capabilities
            config.on_attach = on_attach
            require('lspconfig')[server_name].setup(config)
        end
    }

    -- Turn on lsp status information
    require('fidget').setup()
end

return M
