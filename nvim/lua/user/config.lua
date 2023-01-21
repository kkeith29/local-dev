local packer_install_path = vim.fn.stdpath('data') .. '/site/pack/packer/start/packer.nvim'
local changed_cache_file = vim.fn.stdpath('data') .. '/user_config_changes'
local changed_autocmd_registered = false
local changed = {}
local config_pattern = vim.fn.resolve(vim.fn.stdpath('config')) .. '/lua/user/configs/*.lua'

local state = {}

local function install(use)
    for _, name in ipairs(state.install) do
        state.configs[name].install(use)
    end
end

local function set_options()
    for _, name in ipairs(state.options) do
        state.configs[name].options()
    end
end

local function bind_keymaps()
    for _, name in ipairs(state.keymaps) do
        state.configs[name].keymaps()
    end
end

local function configure()
    for _, name in ipairs(state.configures) do
        state.configs[name].configure()
    end
end

local function load_changed()
    changed = {}
    if vim.fn.filereadable(changed_cache_file) == 1 then
        local file = io.open(changed_cache_file, 'r')
        if file then
            local data = file:read('a')
            changed = vim.fn.json_decode(data)
            file:close()
        end
    end
end

local function clear_changed()
    changed = {}
    if vim.fn.filereadable(changed_cache_file) == 1 then
        os.remove(changed_cache_file)
    end
end

local function save_changed()
    local file = io.open(changed_cache_file, 'w')
    if file then
        file:write(vim.fn.json_encode(changed))
        file:close()
    end
end

local function mark_changed(file)
    if not changed_autocmd_registered then
        local group = vim.api.nvim_create_augroup('UserConfigChanged', { clear = true })
        vim.api.nvim_create_autocmd('VimLeavePre', {
            callback = function()
                save_changed()
            end,
            group = group,
            pattern = '*'
        })
        changed_autocmd_registered = true
    end
    changed[file] = true
end

local function run_packer(packer, sync, complete_callback)
    packer.startup(function(use)
        install(use)
        if sync then
            if complete_callback then
                vim.api.nvim_create_autocmd('User', {
                    callback = complete_callback,
                    pattern = 'PackerComplete',
                    once = true
                })
            end
            packer.sync()
        end
    end)
end

local function reset_state()
    for _, key in ipairs({'configs', 'names', 'install', 'options', 'keymaps', 'configures'}) do
        state[key] = {}
    end
end

local function get_config_name(file)
    return string.match(file, '/([^/]+)%.lua$')
end

local function load()
    reset_state()
    load_changed()

    local config_files = vim.fn.split(vim.fn.glob(config_pattern), '\n')
    local has_change = false
    for _, config_file in ipairs(config_files) do
        if not has_change and changed[config_file] then
            has_change = true
        end
        local module = dofile(config_file)
        if module.enabled == nil or module.enabled then
            state.configs[config_file] = module
        end
    end

    clear_changed()

    local loaded = vim.tbl_keys(state.configs)
    table.sort(loaded, function (a, b)
        return (state.configs[a].priority or 5) > (state.configs[b].priority or 5)
    end)

    for _, file in ipairs(loaded) do
        local module = state.configs[file]
        if module.install then
            table.insert(state.install, file)
        end
        if module.options then
            table.insert(state.options, file)
        end
        if module.keymaps then
            table.insert(state.keymaps, file)
        end
        if module.configure then
            table.insert(state.configures, file)
        end
    end

    return has_change
end

return {
    init = function()
        local packer_installed = vim.fn.empty(vim.fn.glob(packer_install_path)) == 0
        if not packer_installed then
            vim.fn.system { 'git', 'clone', '--depth', '1', 'https://github.com/wbthomason/packer.nvim', packer_install_path }
            vim.cmd [[packadd packer.nvim]]
        end

        local packer = require('packer')
        packer.init({
            compile_path = vim.fn.stdpath('data') .. '/site/plugin/packer_compiled.lua',
            max_jobs = 10,
            display = {
                open_fn = function()
                    return require('packer.util').float({ border = 'solid' })
                end,
            }
        })

        local config_changed = load()
        local sync = not packer_installed or config_changed
        run_packer(packer, sync, function()
            configure()
            set_options()
            bind_keymaps()
        end)

        if sync then
            print '==================================================='
            print '    Plugins are being installed and configured'
            print '           Restart Neovim once complete'
            print '==================================================='
            return
        end

        configure()
        set_options()
        bind_keymaps()

        -- setup auto command to startup packer if config file changes
        local config_group = vim.api.nvim_create_augroup('UserConfig', { clear = true })
        vim.api.nvim_create_autocmd('BufWritePost', {
            callback = function(ev)
                mark_changed(ev.file)
                load()
                run_packer(packer, true, function()
                    local module, name = state.configs[ev.file], get_config_name(ev.file)
                    if module.configure then
                        vim.notify(string.format('[%s] Configuring plugins', name))
                        module.configure()
                    end
                    if module.options then
                        vim.notify(string.format('[%s] Setting up options', name))
                        module.options()
                    end
                    if module.keymaps then
                        vim.notify(string.format('[%s] Binding keymaps', name))
                        module.keymaps()
                    end
                end)
            end,
            group = config_group,
            pattern = config_pattern
        })
    end
}
