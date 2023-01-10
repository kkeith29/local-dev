local M = {}

function M.install(use)
    use 'mfussenegger/nvim-dap'
    use 'rcarriga/nvim-dap-ui'
    use 'theHamsta/nvim-dap-virtual-text'
end

function M.keymaps()
    local km = require('user.keymap')

    km.nnoremap('<leader>db', "<cmd>lua require('dap').toggle_breakpoint()<cr>", '[D]ebug - Toggle [B]reakpoint')
    km.nnoremap('<leader>dc', "<cmd>lua require('dap').continue()<cr>", '[D]ebug - [C]ontinue');
    km.nnoremap('<leader>dn', "<cmd>lua require('dap').step_over()<cr>", '[D]ebug - [N]ext (Step Over)');
    km.nnoremap('<leader>di', "<cmd>lua require('dap').step_into()<cr>", '[D]ebug - Step [I]nto');
    km.nnoremap('<leader>do', "<cmd>lua require('dap').step_out()<cr>", '[D]ebug - Step [O]ut');
end

function M.configure()
    local dap = require('dap')
    local ui = require('dapui')

    vim.fn.sign_define('DapBreakpoint', {text='', texthl='DapUIBreakpointSign', linehl='', numhl=''})

    ui.setup()
    require('nvim-dap-virtual-text').setup({
        enabled = true
    })

    local dapui_group = vim.api.nvim_create_augroup('UserDapUI', { clear = true })
    vim.api.nvim_create_autocmd('FileType', {
        command = 'set nobuflisted',
        group = dapui_group,
        pattern = 'dap-repl'
    })

    local neotree = require('neo-tree')
    local neotree_open = false
    local ui_open = false
    local function open_ui()
        neotree_open = neotree.close('filesystem')
        ui.open({})
        ui_open = true
    end
    local function close_ui()
        if not ui_open then
            return
        end
        ui.close({})
        ui_open = false
        if neotree_open then
            neotree.show('filesystem')
        end
    end
    local function toggle_ui()
        if ui_open then
            close_ui()
        else
            open_ui()
        end
    end

    require('user.keymap').nnoremap('<leader>du', function() toggle_ui() end, '[D]ebug - Toggle [U]I');

    dap.listeners.after.event_initialized['dapui_config'] = function()
        open_ui()
    end
    dap.listeners.before.event_terminated['dapui_config'] = function()
        close_ui()
    end
    dap.listeners.before.event_exited['dapui_config'] = function()
        close_ui()
    end

    dap.adapters.php = {
        type = 'executable',
        command = vim.fn.stdpath('data') .. '/mason/bin/php-debug-adapter'
    }

    dap.configurations.php = {
        {
            type = 'php',
            request = 'launch',
            name = 'Listen for Xdebug',
            port = 9003
        }
    }
end

return M
