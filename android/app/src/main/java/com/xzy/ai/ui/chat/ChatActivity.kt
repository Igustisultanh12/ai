package com.xzy.ai.ui.chat

import android.animation.ObjectAnimator
import android.os.Bundle
import android.view.View
import android.view.animation.AccelerateDecelerateInterpolator
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.core.widget.addTextChangedListener
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.xzy.ai.databinding.ActivityChatBinding
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class ChatActivity : AppCompatActivity() {
    private lateinit var binding: ActivityChatBinding
    private val viewModel: ChatViewModel by viewModels()
    private lateinit var messageAdapter: MessageAdapter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityChatBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupUI()
        setupObservers()
        loadInitialData()
    }

    private fun setupUI() {
        // Setup toolbar
        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "AI Chat"

        // Setup RecyclerView
        messageAdapter = MessageAdapter()
        binding.recyclerViewMessages.apply {
            layoutManager = LinearLayoutManager(this@ChatActivity).apply {
                stackFromEnd = true
            }
            adapter = messageAdapter
            itemAnimator?.changeDuration = 300
        }

        // Setup input
        binding.editTextMessage.addTextChangedListener {
            binding.buttonSend.isEnabled = !it.isNullOrBlank()
            animateSendButton()
        }

        binding.buttonSend.setOnClickListener {
            sendMessage()
        }

        binding.buttonAttach.setOnClickListener {
            // TODO: Implement file attachment
        }

        binding.buttonVoice.setOnClickListener {
            // TODO: Implement voice input
        }
    }

    private fun setupObservers() {
        lifecycleScope.launch {
            viewModel.messages.collect { messages ->
                messageAdapter.submitList(messages)
                if (messages.isNotEmpty()) {
                    binding.recyclerViewMessages.smoothScrollToPosition(messages.size - 1)
                }
                updateEmptyState(messages.isEmpty())
            }
        }

        lifecycleScope.launch {
            viewModel.isLoading.collect { isLoading ->
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
                binding.buttonSend.isEnabled = !isLoading && !binding.editTextMessage.text.isNullOrBlank()
            }
        }

        lifecycleScope.launch {
            viewModel.error.collect { error ->
                error?.let {
                    // Show error snackbar
                    com.google.android.material.snackbar.Snackbar.make(
                        binding.root,
                        it,
                        com.google.android.material.snackbar.Snackbar.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    private fun loadInitialData() {
        val conversationId = intent.getStringExtra(EXTRA_CONVERSATION_ID)
        if (conversationId != null) {
            viewModel.loadConversation(conversationId)
        } else {
            viewModel.createNewConversation()
        }
    }

    private fun sendMessage() {
        val text = binding.editTextMessage.text.toString().trim()
        if (text.isNotEmpty()) {
            viewModel.sendMessage(text)
            binding.editTextMessage.text?.clear()
            animateMessageSent()
        }
    }

    private fun animateSendButton() {
        val scale = if (binding.editTextMessage.text.isNullOrBlank()) 0.8f else 1.0f
        binding.buttonSend.animate()
            .scaleX(scale)
            .scaleY(scale)
            .setDuration(150)
            .setInterpolator(AccelerateDecelerateInterpolator())
            .start()
    }

    private fun animateMessageSent() {
        ObjectAnimator.ofFloat(binding.cardInput, "translationY", 0f, -20f, 0f).apply {
            duration = 300
            interpolator = AccelerateDecelerateInterpolator()
            start()
        }
    }

    private fun updateEmptyState(isEmpty: Boolean) {
        binding.emptyStateLayout.visibility = if (isEmpty) View.VISIBLE else View.GONE
    }

    override fun onSupportNavigateUp(): Boolean {
        finish()
        return true
    }

    companion object {
        const val EXTRA_CONVERSATION_ID = "conversation_id"
    }
}