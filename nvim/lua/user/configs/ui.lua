local M = {}

function M.install(use)
    use 'rcarriga/nvim-notify'
end

function M.configure()
    vim.notify = require('notify')
end

return M
