local M = {}

function M.install(use)
    use 'mfussenegger/nvim-dap'
    use 'rcarriga/nvim-dap-ui'
    use 'theHamsta/nvim-dap-virtual-text'
end

function M.configure()
    local dap = require('dap')
    local ui = require('dapui')

    vim.fn.sign_define('DapBreakpoint', {text='', texthl='DapBreakpointSign', linehl='', numhl=''})
    vim.fn.sign_define('DapBreakpointCondition', {text='', texthl='DapBreakpointConditionalSign', linehl='', numhl=''})
    vim.fn.sign_define('DapStopped', {text='', texthl='DapStoppedSign', linehl='CursorLine', numhl=''})

    ui.setup({
        floating = {
            max_width = 0.8,
            max_height = 0.8
        },
        layouts = {
            {
                elements = {
                    'watches', 'scopes'
                },
                size = 60,
                position = 'right'
            }
        }
    })
    require('nvim-dap-virtual-text').setup({})

    vim.api.nvim_create_autocmd('FileType', {
        group = vim.api.nvim_create_augroup('UserDap', { clear = true }),
        command = 'set nobuflisted',
        pattern = 'dap-repl'
    })

    local function open_ui()
        ui.open({})
    end
    local function close_ui()
        ui.close({})
    end
    local vt = require('nvim-dap-virtual-text.virtual_text')
    local function cleanup()
        vt.clear_virtual_text()
    end
    dap.listeners.after.event_initialized['dapui_config'] = open_ui
    dap.listeners.before.event_thread['dapui_config'] = function(_, body)
        if body then
            if body.reason == 'started' then
                open_ui()
            elseif body.reason == 'exited' then
                close_ui()
                cleanup()
            end
        end
    end
    dap.listeners.before.event_terminated['dapui_config'] = close_ui
    dap.listeners.before.event_exited['dapui_config'] = close_ui

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

function M.keymaps()
    local km = require('user.keymap')

    local dap = require('dap')
    local telescope = require('telescope')
    local ui = require('dapui')
    local vt = require('nvim-dap-virtual-text.virtual_text')
    -- dj, dk, df, dl in use by diagonistics
    km.nnoremap('<leader>dbt', dap.toggle_breakpoint, '[D]ebug - Toggle [B]reakpoint', { repeatable = true })
    km.nnoremap('<leader>dbl', telescope.extensions.dap.list_breakpoints, '[D]ebug - [B]reakpoint [L]ist')
    km.nnoremap('<leader>dso', dap.step_over, '[D]ebug - [S]tep [O]ver', { repeatable = true })
    km.nnoremap('<leader>dsi', dap.step_into, '[D]ebug - [S]tep [i]nto', { repeatable = true })
    km.nnoremap('<leader>dsI', dap.step_out, '[D]ebug - [S]tep Out[I]', { repeatable = true })
    km.nnoremap('<leader>dc', dap.continue, '[D]ebug - [C]ontinue', { repeatable = true })
    km.nnoremap('<leader>dut', ui.toggle, '[D]ebug [U]I - Toggle');
    km.nnoremap('<leader>duf', telescope.extensions.dap.frames, '[D]ebug [U]I - Display [F]rames')
    km.nnoremap('<leader>dur', function() ui.float_element('repl', { enter = true }) end, '[D]ebug [U]I - Display [R]EPL')
    km.nnoremap('<leader>duc', function() ui.float_element('console', { enter = true }) end, '[D]ebug [U]I - Display [C]onsole')
    km.nnoremap('<leader>dt', dap.terminate, '[D]ebug - [T]erminate')
    km.nnoremap('<leader>dd', function()
        dap.close()
        dap.disconnect(nil, function()
            ui.close({})
            vt.clear_virtual_text()
        end)
    end, '[D]ebug - [D]isconnect')
end

return M
