local M = {
    enabled = true
}

function M.install(use)
    use {
        'nvim-neotest/neotest',
        requires = {
            'nvim-lua/plenary.nvim',
            'nvim-treesitter/nvim-treesitter',
            'antoinemadec/FixCursorHold.nvim',
            'olimorris/neotest-phpunit'
        }
    }
end

function M.configure()
    require('neotest').setup({
        adapters = {
            require('neotest-phpunit')
        },
        output = {
            enabled = true,
            open_on_run = true
        },
        quickfix = {
            enabled = false
        }
    })
end

function M.keymaps()
    local km = require('user.keymap')
    local neotest = require('neotest')

    km.nnoremap('<leader>tc', neotest.run.run, '[T]est - [C]losest')
    km.nnoremap('<leader>tf', function() neotest.run.run(vim.fn.expand('%')) end, '[T]est - [F]ile')
    km.nnoremap('<leader>ts', function() neotest.run.run({ suite = true }) end, '[T]est - [F]ile')
end

return M
