local M = {
    enabled = true
}

function M.install(use)
    use 'lukas-reineke/indent-blankline.nvim' -- Add indentation guides even on blank lines
    use 'numToStr/Comment.nvim' -- "gc" to comment visual regions/lines
    use 'tpope/vim-sleuth' -- Detect tabstop and shiftwidth automatically
    use 'famiu/bufdelete.nvim'

    use {
        'kylechui/nvim-surround',
        tag = '*', -- Use for stability; omit to use `main` branch for the latest features
    }

    use 'windwp/nvim-autopairs'

    use { 'akinsho/bufferline.nvim', tag = "v3.*", requires = 'nvim-tree/nvim-web-devicons' }

    use 'karb94/neoscroll.nvim'

    use 'folke/zen-mode.nvim'
end

function M.configure()
    require('Comment').setup({
        mappings = false
    })

    require('indent_blankline').setup({
        show_end_of_line = true,
        show_trailing_blankline_indent = false,
    })

    require('nvim-surround').setup()

    require('nvim-autopairs').setup()

    local cf = require('nightfox.palette.carbonfox').palette

    require('bufferline').setup({
        options = {
            show_buffer_close_icons = false,
            show_close_icons = false,
            offsets = {
                {
                    filetype = 'neo-tree',
                    text = 'File Explorer',
                    text_align = 'left',
                    separator = true
                }
            },
            separator_style = 'slant'
        },
        highlights = {
            fill = {bg = cf.bg0, fg = cf.white.base},
            background = {fg = cf.blue.base, bg = cf.bg2},

            offset_separator = {bg = cf.bg0, fg = cf.bg0},

            separator = {fg = cf.bg0, bg = cf.bg2},
            separator_visible = {fg = cf.bg0, bg = cf.bg1},
            separator_selected = {fg = cf.bg0},

            buffer_visible = {fg = cf.blue.base, bg = cf.bg1},
            buffer_selected = {fg = cf.white.base, bg = cf.bg1},

            modified = {fg = cf.yellow.dim, bg = cf.bg2},
            modified_visible = {fg = cf.yellow.dim, bg = cf.bg1},
            modified_selected = {fg = cf.yellow.bright, bg = cf.bg1},
        }
    })

    require('neoscroll').setup({
        mappings = {}
    })

    require('zen-mode').setup({
        window = {
            width = 0.6
        },
        plugins = {
            gitsigns = {
                enabled = true
            },
            tmux = {
                enabled = false
            }
        }
    })
end

function M.options()
    vim.opt.list = true
    vim.opt.listchars:append "eol:↴"
end

function M.keymaps()
    vim.keymap.set({ 'n', 'v' }, '<Leader>cc', require('Comment.api').toggle.linewise.current, { noremap = true })
    local esc = vim.api.nvim_replace_termcodes('<ESC>', true, false, true)

    -- Toggle selection (linewise)
    vim.keymap.set('x', '<Leader>cc', function()
        vim.api.nvim_feedkeys(esc, 'nx', false)
        require('Comment.api').toggle.linewise(vim.fn.visualmode())
    end)

    local km = require('user.keymap')
    local neoscroll = require('neoscroll')
    km.nnoremap('<C-j>', function() neoscroll.scroll(0.5, true, 250) end, 'Move down half page')
    km.nnoremap('<C-k>', function() neoscroll.scroll(-0.5, true, 250) end, 'Move up half page')
    km.nnoremap('zz', function() neoscroll.zz(250) end, 'Recenter cursor on page')

    local zenmode = require('zen-mode')
    km.nnoremap('<leader>z', function() zenmode.toggle() end, 'Toggle [Z]en mode')
end

return M
