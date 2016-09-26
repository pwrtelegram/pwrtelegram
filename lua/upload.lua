#!/usr/bin/lua

args = {}
index = 1 
for value in string.gmatch(arg[1],"[^%s]+") do args [index] = value index = index + 1 end 
--inspect = require('inspect')

CONTACT_NAME = args[1] -- It can be a part of contacts na
path = args[2]
type = args[3]
MESSAGE_COUNT = 500

function sleep(n)
  os.execute("sleep " .. tonumber(n))
end

function msg_callback(extra, success, result)
  if success and result ~= false then
    if type == 'document' then
      reply_document(result.id, path, callback, false)
    end
    if type == 'photo' then
      reply_photo(user, path, callback, false)
    end
    if type == 'video' then
      reply_video(result.id, path, callback, false)
    end
    if type == 'audio' then
      reply_audio(result.id, path, callback, false)
    end
  end
--  os.exit()
end

function callback(extra, success, result)
  if success and result ~= false then
    print('{"event":"upload", "result":"'..result..'"}')
    os.exit()
  end
end

function dialogs_cb(extra, success, dialog)
 if success then
  for _,d in pairs(dialog) do
   v = d.peer
   if (v.username == CONTACT_NAME and v.peer_type == "user") then
    user='user#id'..v.peer_id
    send_msg (user, 'shish', msg_callback, false)
   end
  end
 end 
end



function history_cb(extra, success, history)
end

function user_cb(extra, success, user)
end


function on_binlog_replay_end ()
  get_dialog_list(dialogs_cb, contacts_extra)
end

function on_msg_receive (msg)
  get_dialog_list(dialogs_cb, contacts_extra)
end

function on_our_id (id)
end

function on_secret_chat_created (peer)
end

function on_user_update (user)
end

function on_chat_update (user)
end

function on_get_difference_end ()
end
