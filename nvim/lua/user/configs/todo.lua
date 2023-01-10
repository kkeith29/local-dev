local M = {}

function M.install(use)
    use {
        'phaazon/mind.nvim',
        branch = 'v2.2',
        requires = { 'nvim-lua/plenary.nvim' }
    }
end

function M.configure()
    require('mind').setup()
end

return M
